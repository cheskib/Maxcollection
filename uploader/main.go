// Max Collection uploader agent.
//
// The only custom code on a scan-station PC. Watches the scans folder
// (per-bag subfolders created by PaperStream), pushes each finished JPEG
// to the Collection server with a checksum, and moves confirmed files to
// the sent folder. It never touches the scanner and never deletes a file
// without the server's acknowledgment.
//
// Build (from the repo root):
//
//	cd uploader && GOOS=windows GOARCH=amd64 go build -ldflags="-H windowsgui -s -w" -o maxcollection-uploader.exe .
package main

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"
)

type config struct {
	Server   string `json:"server"`
	Token    string `json:"token"`
	WatchDir string `json:"watch_dir"`
	SentDir  string `json:"sent_dir"`
}

// A file is "stable" once its size stops changing between polls, so a
// JPEG still being written by PaperStream is never uploaded half-done.
type candidate struct {
	size    int64
	seenAt  time.Time
	stable  bool
	retryAt time.Time
}

const pollInterval = 2 * time.Second

func main() {
	exeDir := executableDir()
	logFile := setupLog(exeDir)
	if logFile != nil {
		defer logFile.Close()
	}

	cfg, err := loadConfig(filepath.Join(exeDir, "uploader.json"))
	if err != nil {
		log.Fatalf("cannot read uploader.json: %v", err)
	}

	log.Printf("watching %s -> %s", cfg.WatchDir, cfg.Server)

	seen := map[string]*candidate{}

	for {
		scanOnce(cfg, seen)
		time.Sleep(pollInterval)
	}
}

func scanOnce(cfg config, seen map[string]*candidate) {
	_ = filepath.Walk(cfg.WatchDir, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			return nil
		}

		ext := strings.ToLower(filepath.Ext(path))
		if ext != ".jpg" && ext != ".jpeg" {
			return nil
		}

		entry, known := seen[path]
		if !known {
			seen[path] = &candidate{size: info.Size(), seenAt: time.Now()}
			return nil
		}

		if entry.size != info.Size() {
			// Still being written; reset and keep waiting.
			entry.size = info.Size()
			entry.stable = false
			return nil
		}

		if time.Now().Before(entry.retryAt) {
			return nil
		}

		if err := upload(cfg, path); err != nil {
			// Retry with a flat one-minute backoff, forever. The file
			// stays on disk untouched until the server confirms it.
			log.Printf("upload failed (will retry): %s: %v", path, err)
			entry.retryAt = time.Now().Add(time.Minute)
			return nil
		}

		if err := moveToSent(cfg, path); err != nil {
			log.Printf("uploaded but could not move to sent: %s: %v", path, err)
		}
		delete(seen, path)

		return nil
	})
}

func upload(cfg config, path string) error {
	data, err := os.ReadFile(path)
	if err != nil {
		return err
	}

	sum := sha256.Sum256(data)

	relative, err := filepath.Rel(cfg.WatchDir, path)
	if err != nil {
		return err
	}
	folder := filepath.Dir(relative)
	if folder == "." {
		folder = "loose"
	}
	// Nested folders flatten to their top level: scans\BAG-000123\x.jpg -> BAG-000123
	folder = strings.Split(filepath.ToSlash(folder), "/")[0]

	body := &bytes.Buffer{}
	writer := multipart.NewWriter(body)
	_ = writer.WriteField("folder", folder)
	_ = writer.WriteField("filename", filepath.Base(path))
	_ = writer.WriteField("checksum", hex.EncodeToString(sum[:]))

	part, err := writer.CreateFormFile("file", filepath.Base(path))
	if err != nil {
		return err
	}
	if _, err := part.Write(data); err != nil {
		return err
	}
	if err := writer.Close(); err != nil {
		return err
	}

	request, err := http.NewRequest(http.MethodPost, strings.TrimRight(cfg.Server, "/")+"/api/ingest", body)
	if err != nil {
		return err
	}
	request.Header.Set("Content-Type", writer.FormDataContentType())
	request.Header.Set("X-Station-Token", cfg.Token)
	request.Header.Set("Accept", "application/json")

	client := &http.Client{Timeout: 2 * time.Minute}
	response, err := client.Do(request)
	if err != nil {
		return err
	}
	defer response.Body.Close()

	// 201 stored, 200 duplicate (already safely there) — both are success.
	if response.StatusCode == http.StatusCreated || response.StatusCode == http.StatusOK {
		log.Printf("sent %s/%s", folder, filepath.Base(path))
		return nil
	}

	detail, _ := io.ReadAll(io.LimitReader(response.Body, 512))

	return fmt.Errorf("server said %d: %s", response.StatusCode, strings.TrimSpace(string(detail)))
}

// The sent folder mirrors the scans folder's per-bag structure, forming
// the local on-site backup.
func moveToSent(cfg config, path string) error {
	relative, err := filepath.Rel(cfg.WatchDir, path)
	if err != nil {
		return err
	}

	destination := filepath.Join(cfg.SentDir, relative)
	if err := os.MkdirAll(filepath.Dir(destination), 0o755); err != nil {
		return err
	}

	return os.Rename(path, destination)
}

func loadConfig(path string) (config, error) {
	var cfg config

	data, err := os.ReadFile(path)
	if err != nil {
		return cfg, err
	}
	if err := json.Unmarshal(data, &cfg); err != nil {
		return cfg, err
	}
	if cfg.Server == "" || cfg.Token == "" || cfg.WatchDir == "" || cfg.SentDir == "" {
		return cfg, fmt.Errorf("uploader.json must set server, token, watch_dir, and sent_dir")
	}

	return cfg, nil
}

func executableDir() string {
	exe, err := os.Executable()
	if err != nil {
		return "."
	}

	return filepath.Dir(exe)
}

// Log beside the executable so problems are diagnosable at the station.
func setupLog(dir string) *os.File {
	file, err := os.OpenFile(filepath.Join(dir, "uploader.log"), os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o644)
	if err != nil {
		return nil
	}
	log.SetOutput(io.MultiWriter(os.Stdout, file))

	return file
}
