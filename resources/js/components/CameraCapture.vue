<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    step: 'front' | 'back' | 'extra';
    frontThumb: string | null;
    backThumb: string | null;
    detailCount: number;
    canFinish: boolean;
    uploading: boolean;
}>();

const emit = defineEmits<{
    (e: 'photo', file: File): void;
    (e: 'gallery'): void;
    (e: 'finish'): void;
    (e: 'exit'): void;
    (e: 'unsupported'): void;
}>();

const video = ref<HTMLVideoElement | null>(null);
const showHelp = ref(false);
const torchSupported = ref(false);
const torchOn = ref(false);
const zoomSupported = ref(false);
const zoomLevel = ref(1);

let stream: MediaStream | null = null;
let track: MediaStreamTrack | null = null;

const STEP_TITLES: Record<string, string> = {
    front: 'Line up the FRONT of the card',
    back: 'Now the BACK',
    extra: 'Any close-ups? Or tap ✓ Done',
};

onMounted(async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 2560 }, height: { ideal: 1920 } },
            audio: false,
        });
    } catch {
        emit('unsupported');
        return;
    }

    if (video.value) {
        video.value.srcObject = stream;
        try {
            await video.value.play();
        } catch {
            // Autoplay hiccups resolve on the next user interaction.
        }
    }

    track = stream.getVideoTracks()[0] ?? null;
    // Flash and zoom exist only where the device+browser expose them
    // (most Android Chrome; rarely iPhone Safari) — buttons appear only
    // when they will actually work.
    const capabilities = (track?.getCapabilities?.() ?? {}) as Record<string, unknown>;
    torchSupported.value = Boolean(capabilities.torch);
    const zoomRange = capabilities.zoom as { max?: number } | undefined;
    zoomSupported.value = typeof zoomRange === 'object' && (zoomRange.max ?? 1) >= 2;
});

onBeforeUnmount(() => {
    stream?.getTracks().forEach((entry) => entry.stop());
});

async function toggleTorch(): Promise<void> {
    if (!track) return;
    torchOn.value = !torchOn.value;
    try {
        await track.applyConstraints({ advanced: [{ torch: torchOn.value } as MediaTrackConstraintSet] });
    } catch {
        torchOn.value = false;
    }
}

async function setZoom(level: number): Promise<void> {
    if (!track) return;
    zoomLevel.value = level;
    try {
        await track.applyConstraints({ advanced: [{ zoom: level } as MediaTrackConstraintSet] });
    } catch {
        zoomLevel.value = 1;
    }
}

function shutter(): void {
    const source = video.value;
    if (!source || props.uploading || source.videoWidth === 0) return;

    const canvas = document.createElement('canvas');
    canvas.width = source.videoWidth;
    canvas.height = source.videoHeight;
    canvas.getContext('2d')?.drawImage(source, 0, 0);
    canvas.toBlob(
        (blob) => {
            if (blob) emit('photo', new File([blob], 'capture.jpg', { type: 'image/jpeg' }));
        },
        'image/jpeg',
        0.92,
    );
}
</script>

<template>
    <div class="fixed inset-0 z-40 flex flex-col bg-black">
        <div class="flex items-start justify-between px-4 pb-2 pt-4">
            <button class="flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-lg text-white" @click="emit('exit')">
                ←
            </button>

            <div class="flex gap-3">
                <div class="text-center">
                    <p class="text-sm font-semibold" :class="step === 'front' ? 'text-white' : 'text-white/50'">Front</p>
                    <div
                        class="mt-1 h-16 w-12 overflow-hidden rounded-lg border-2"
                        :class="step === 'front' ? 'border-blue-400' : 'border-dashed border-white/50'"
                    >
                        <img v-if="frontThumb" :src="frontThumb" class="h-full w-full object-cover" alt="Front" />
                    </div>
                </div>
                <div class="text-center">
                    <p class="text-sm font-semibold" :class="step === 'back' ? 'text-white' : 'text-white/50'">Back</p>
                    <div
                        class="relative mt-1 h-16 w-12 overflow-hidden rounded-lg border-2"
                        :class="step === 'back' ? 'border-blue-400' : 'border-dashed border-white/50'"
                    >
                        <img v-if="backThumb" :src="backThumb" class="h-full w-full object-cover" alt="Back" />
                    </div>
                </div>
                <div v-if="detailCount > 0" class="self-end pb-1 text-xs font-semibold text-white/80">+{{ detailCount }}</div>
            </div>

            <div class="flex gap-2">
                <button class="flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-white" @click="showHelp = !showHelp">
                    ?
                </button>
                <button
                    v-if="torchSupported"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-white"
                    :class="torchOn ? 'bg-yellow-400 text-gray-900' : 'bg-white/20'"
                    @click="toggleTorch"
                >
                    ⚡
                </button>
            </div>
        </div>

        <div class="relative flex-1 overflow-hidden">
            <video ref="video" autoplay playsinline muted class="absolute inset-0 h-full w-full object-cover"></video>

            <!-- Corner brackets sized to a trading card's 2.5 x 3.5 ratio -->
            <div class="pointer-events-none absolute left-1/2 top-1/2 aspect-[5/7] w-[72%] -translate-x-1/2 -translate-y-1/2">
                <div class="absolute left-0 top-0 h-10 w-10 border-l-4 border-t-4 border-white/90"></div>
                <div class="absolute right-0 top-0 h-10 w-10 border-r-4 border-t-4 border-white/90"></div>
                <div class="absolute bottom-0 left-0 h-10 w-10 border-b-4 border-l-4 border-white/90"></div>
                <div class="absolute bottom-0 right-0 h-10 w-10 border-b-4 border-r-4 border-white/90"></div>
            </div>

            <p class="absolute inset-x-0 top-3 text-center text-sm font-semibold text-white drop-shadow">
                {{ STEP_TITLES[step] }}
            </p>

            <div v-if="showHelp" class="absolute inset-x-6 top-12 rounded-xl bg-black/80 p-4 text-sm text-white" @click="showHelp = false">
                <p class="font-semibold">Tips</p>
                <ul class="mt-2 list-disc space-y-1 pl-4 text-white/90">
                    <li>Fill the brackets with the card, straight on.</li>
                    <li>Avoid glare — tilt slightly or turn off harsh light.</li>
                    <li>Front first, then the back; add close-ups after.</li>
                </ul>
                <p class="mt-2 text-xs text-white/60">Tap to close</p>
            </div>

            <p v-if="uploading" class="absolute inset-x-0 bottom-3 text-center text-sm font-semibold text-white drop-shadow">Uploading…</p>
        </div>

        <div v-if="zoomSupported" class="flex justify-center pb-2 pt-2">
            <div class="flex gap-1 rounded-full bg-white/20 p-1">
                <button
                    class="rounded-full px-3 py-1 text-sm font-semibold"
                    :class="zoomLevel === 1 ? 'bg-black/60 text-yellow-400' : 'text-white'"
                    @click="setZoom(1)"
                >
                    1x
                </button>
                <button
                    class="rounded-full px-3 py-1 text-sm font-semibold"
                    :class="zoomLevel === 2 ? 'bg-black/60 text-yellow-400' : 'text-white'"
                    @click="setZoom(2)"
                >
                    2x
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between bg-black px-8 pb-8 pt-2">
            <button class="flex h-12 w-12 items-center justify-center rounded-lg bg-white/15 text-xl text-white" @click="emit('gallery')">
                🖼
            </button>
            <button
                :disabled="uploading"
                class="h-18 w-18 rounded-full border-4 border-white p-1 disabled:opacity-50"
                style="height: 4.5rem; width: 4.5rem"
                @click="shutter"
            >
                <span class="block h-full w-full rounded-full bg-white"></span>
            </button>
            <button
                v-if="canFinish"
                class="rounded-full bg-green-600 px-4 py-2.5 text-sm font-semibold text-white"
                @click="emit('finish')"
            >
                ✓ Done
            </button>
            <span v-else class="h-12 w-12"></span>
        </div>
    </div>
</template>
