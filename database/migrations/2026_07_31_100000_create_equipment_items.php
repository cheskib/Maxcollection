<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The Equipment page becomes live data: admins add items and flip
// statuses as gear arrives. Seeded with the reviewed shopping list so
// production starts populated.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_items', function (Blueprint $table) {
            $table->id();
            $table->string('station'); // comic_photo | card_scan | bagging | printing | everywhere
            $table->string('name');
            $table->string('note')->nullable();
            $table->string('status'); // have | ordered | need | later
            $table->string('price')->nullable();
            $table->json('links')->nullable(); // [{label, url}]
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['station', 'sort_order']);
        });

        $seed = function (string $station, array $items) {
            foreach ($items as $order => $item) {
                DB::table('equipment_items')->insert([
                    'station' => $station,
                    'name' => $item[0],
                    'note' => $item[1],
                    'status' => $item[2],
                    'price' => $item[3] ?? null,
                    'links' => isset($item[4]) ? json_encode($item[4]) : null,
                    'sort_order' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        };

        $seed('comic_photo', [
            ['Canon EOS R50 + 18-45mm kit', '24 MP, USB tether control, electronic shutter = zero wear', 'need', '$799', [
                ['label' => 'B&H', 'url' => 'https://www.bhphotovideo.com/c/product/1750100-REG/canon_5812c012_eos_r50_with_rf_s.html'],
                ['label' => 'Amazon', 'url' => 'https://www.amazon.com/Canon-Mirrorless-RF-S18-45mm-Smartphone-Connection/dp/B0BTTV6CT1'],
            ]],
            ['Impact Turtle Base C-stand, 10.75\' + 40" grip arm', 'Holds the camera dead still above the panel — add a sandbag', 'need', '$169', [
                ['label' => 'B&H', 'url' => 'https://www.bhphotovideo.com/c/product/491430-REG/Impact_Master_Century_C_Stand.html'],
            ]],
            ['Godox SL60II-Bi 2-light kit with softboxes + stands', 'Diffuse light from both sides at 45° — kills glare on glossy covers', 'need', '$300–340', [
                ['label' => 'Amazon', 'url' => 'https://www.amazon.com/SL60II-Bi-2800K-6500K-Continuous-Honeycomb-Recording/dp/B0C3XS3Q5L'],
            ]],
            ['USB foot pedal', 'The hands-free shutter — budget footswitch is fine to start', 'need', '$25–99', [
                ['label' => 'Kinesis', 'url' => 'https://www.amazon.com/Kinesis-Corporation-Savant-Elite2-Control/dp/B00QYRTQFI'],
                ['label' => 'Budget', 'url' => 'https://www.amazon.com/s?k=PCsensor+USB+foot+switch+single'],
            ]],
            ['Savage seamless paper 53"×36\'', 'Neutral matte surface under the panels', 'need', '$60–90', [
                ['label' => 'B&H', 'url' => 'https://www.bhphotovideo.com/c/product/45639-REG/Savage_50_1253_Widetone_Seamless_Background_Paper.html'],
                ['label' => 'Amazon', 'url' => 'https://us.amazon.com/Savage-Widetone-Seamless-Background-Microfiber/dp/B084Z2ND8J'],
            ]],
            ['USB-C tether cable, 15\'', 'Camera to controller PC — any quality 15\' USB-C 3.x works', 'need', '$25–70', [
                ['label' => 'Amazon', 'url' => 'https://www.amazon.com/Tether-Tools-TetherPro-USB-C-to/dp/B0794G8TTK'],
            ]],
            ['LP-E17 dummy-battery AC power kit', '⚠ Buy a listing that explicitly names the EOS R50', 'need', '$30–80', [
                ['label' => 'Amazon search', 'url' => 'https://www.amazon.com/s?k=LP-E17+dummy+battery+AC+adapter+EOS+R50'],
            ]],
            ['24"×24" panels', 'Six comics per panel, ~$7 each', 'ordered'],
            ['Controller PC + monitor', 'From existing stock', 'have'],
            ['Touchscreen kiosk monitor', 'A normal monitor + mouse does the job — touch is just a mouse to the software', 'later', '$200'],
        ]);

        $seed('card_scan', [
            ['Fujitsu fi-8170 document scanner', 'Cards front + back, ticket-first batching', 'ordered'],
            ['PC + monitor (+ keyboard as backup)', 'From existing stock', 'have'],
            ['Yellow bins + ticket carriers', '50–70 cards per bin, sticker ticket on top', 'ordered'],
            ['USB speakers', 'Sounds are load-bearing — skip if the monitor has decent ones', 'need', '$15–25'],
        ]);

        $seed('bagging', [
            ['Barcode gun(s)', 'From existing stock', 'have'],
            ['PC + monitor per station', 'From existing stock', 'have'],
            ['SET-ASIDE card', 'Printable from the Bagging screen — laminate it', 'have'],
            ['5-row card house boxes + divider cards', '~3,000 cards per box', 'ordered'],
            ['USB speakers', 'One pair per station', 'need', '$15–25'],
        ]);

        $seed('printing', [
            ['Label printer(s)', 'From existing stock — model determines which rolls to buy', 'have'],
            ['4"×2" sticker stock (removable adhesive)', 'Bag tickets — peel clean off the carrier', 'need', '$40'],
            ['4"×6" label stock', 'Jumbo box label pairs, base + lid', 'need', '$30'],
            ['Laminating pouches', 'SET-ASIDE cards and future badge cards', 'need', '$15'],
        ]);

        $seed('everywhere', [
            ['PCs, monitors, keyboards, mice, accessories', 'From existing stock', 'have'],
            ['Uploader agent + all floor software', 'Built into this system — download from Settings', 'have'],
            ['Dropbox 2TB archive', 'Connected and archiving automatically', 'have'],
            ['UPS battery backup', 'Camera + controller survive a power blink', 'later', '$70'],
            ['Long-range barcode scanner', 'Only if the jumbo box labels disappoint at 4–6 ft with a normal gun', 'later', '$450'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_items');
    }
};
