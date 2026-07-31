<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

// The floor equipment list, by station: what exists, what's on the way,
// what still needs buying (with live links), and what can wait.
type Status = 'have' | 'ordered' | 'need' | 'later';

interface Item {
    name: string;
    note: string;
    status: Status;
    price?: string;
    links?: { label: string; url: string }[];
}

interface Station {
    emoji: string;
    name: string;
    photo: string;
    blurb: string;
    items: Item[];
}

const BADGES: Record<Status, { label: string; classes: string }> = {
    have: { label: '✅ Have', classes: 'bg-green-100 text-green-800' },
    ordered: { label: '🚚 Ordered', classes: 'bg-blue-100 text-blue-800' },
    need: { label: '🛒 Need', classes: 'bg-amber-100 text-amber-800' },
    later: { label: '⏳ Later', classes: 'bg-gray-100 text-gray-600' },
};

const STATIONS: Station[] = [
    {
        emoji: '📚',
        name: 'Comic Photo Station',
        photo: '/images/floor/camera-rig.jpg',
        blurb: 'Fixed overhead camera over the six-comic panel. Everything here is still to buy — this is the one station being built from scratch.',
        items: [
            {
                name: 'Canon EOS R50 + 18-45mm kit',
                note: '24 MP, USB tether control, electronic shutter = zero wear',
                status: 'need',
                price: '$799',
                links: [
                    { label: 'B&H', url: 'https://www.bhphotovideo.com/c/product/1750100-REG/canon_5812c012_eos_r50_with_rf_s.html' },
                    { label: 'Amazon', url: 'https://www.amazon.com/Canon-Mirrorless-RF-S18-45mm-Smartphone-Connection/dp/B0BTTV6CT1' },
                ],
            },
            {
                name: 'Impact Turtle Base C-stand, 10.75′ + 40″ grip arm',
                note: 'Holds the camera dead still above the panel — add a sandbag',
                status: 'need',
                price: '$169',
                links: [{ label: 'B&H', url: 'https://www.bhphotovideo.com/c/product/491430-REG/Impact_Master_Century_C_Stand.html' }],
            },
            {
                name: 'Godox SL60II-Bi 2-light kit with softboxes + stands',
                note: 'Diffuse light from both sides at 45° — kills glare on glossy covers',
                status: 'need',
                price: '$300–340',
                links: [{ label: 'Amazon', url: 'https://www.amazon.com/SL60II-Bi-2800K-6500K-Continuous-Honeycomb-Recording/dp/B0C3XS3Q5L' }],
            },
            {
                name: 'USB foot pedal',
                note: 'The hands-free shutter — budget footswitch is fine to start',
                status: 'need',
                price: '$25–99',
                links: [
                    { label: 'Kinesis', url: 'https://www.amazon.com/Kinesis-Corporation-Savant-Elite2-Control/dp/B00QYRTQFI' },
                    { label: 'Budget', url: 'https://www.amazon.com/s?k=PCsensor+USB+foot+switch+single' },
                ],
            },
            {
                name: 'Savage seamless paper 53″×36′',
                note: 'Neutral matte surface under the panels',
                status: 'need',
                price: '$60–90',
                links: [
                    { label: 'B&H', url: 'https://www.bhphotovideo.com/c/product/45639-REG/Savage_50_1253_Widetone_Seamless_Background_Paper.html' },
                    { label: 'Amazon', url: 'https://us.amazon.com/Savage-Widetone-Seamless-Background-Microfiber/dp/B084Z2ND8J' },
                ],
            },
            {
                name: 'USB-C tether cable, 15′',
                note: 'Camera to controller PC — any quality 15′ USB-C 3.x works',
                status: 'need',
                price: '$25–70',
                links: [{ label: 'Amazon', url: 'https://www.amazon.com/Tether-Tools-TetherPro-USB-C-to/dp/B0794G8TTK' }],
            },
            {
                name: 'LP-E17 dummy-battery AC power kit',
                note: '⚠ Buy a listing that explicitly names the EOS R50',
                status: 'need',
                price: '$30–80',
                links: [{ label: 'Amazon search', url: 'https://www.amazon.com/s?k=LP-E17+dummy+battery+AC+adapter+EOS+R50' }],
            },
            { name: '24″×24″ panels', note: 'Six comics per panel, ~$7 each', status: 'ordered' },
            { name: 'Controller PC + monitor', note: 'From existing stock', status: 'have' },
            {
                name: 'Touchscreen kiosk monitor',
                note: 'The big-button screen — a normal monitor + mouse works until then',
                status: 'later',
                price: '$200',
            },
        ],
    },
    {
        emoji: '🃏',
        name: 'Card Scan Desk',
        photo: '/images/floor/scan-desk.jpg',
        blurb: 'The fi-8170 feeds tickets and cards; the monitor shows status only. Nearly everything is covered.',
        items: [
            { name: 'Fujitsu fi-8170 document scanner', note: 'Cards front + back, ticket-first batching', status: 'ordered' },
            { name: 'PC + monitor (+ keyboard as backup)', note: 'From existing stock', status: 'have' },
            { name: 'Yellow bins + ticket carriers', note: '50–70 cards per bin, sticker ticket on top', status: 'ordered' },
            { name: 'USB speakers', note: 'Sounds are load-bearing — skip if the monitor has decent ones', status: 'need', price: '$15–25' },
        ],
    },
    {
        emoji: '🧤',
        name: 'Bagging & Boxing',
        photo: '/images/floor/bagging.jpg',
        blurb: 'Scan in, scan out — the screens are live in this system already. Hardware is covered from stock.',
        items: [
            { name: 'Barcode gun(s)', note: 'From existing stock', status: 'have' },
            { name: 'PC + monitor per station', note: 'From existing stock', status: 'have' },
            { name: 'SET-ASIDE card', note: 'Printable from the Bagging screen — laminate it', status: 'have' },
            { name: '5-row card house boxes + divider cards', note: '~3,000 cards per box', status: 'ordered' },
            { name: 'USB speakers', note: 'One pair per station', status: 'need', price: '$15–25' },
        ],
    },
    {
        emoji: '🖨️',
        name: 'Printing & Labels',
        photo: '/images/floor/prep.jpg',
        blurb: 'Printers exist — stock has to match them. Confirm the printer model before ordering rolls.',
        items: [
            { name: 'Label printer(s)', note: 'From existing stock — model determines which rolls to buy', status: 'have' },
            { name: '4″×2″ sticker stock (removable adhesive)', note: 'Bag tickets — peel clean off the carrier', status: 'need', price: '$40' },
            { name: '4″×6″ label stock', note: 'Jumbo box label pairs, base + lid', status: 'need', price: '$30' },
            { name: 'Laminating pouches', note: 'SET-ASIDE cards and future badge cards', status: 'need', price: '$15' },
        ],
    },
    {
        emoji: '🖥️',
        name: 'Everywhere',
        photo: '/images/floor/comic-line.jpg',
        blurb: 'Shared infrastructure across the floor.',
        items: [
            { name: 'PCs, monitors, keyboards, mice, accessories', note: 'From existing stock', status: 'have' },
            { name: 'Uploader agent + all floor software', note: 'Built into this system — download from Settings', status: 'have' },
            { name: 'Dropbox 2TB archive', note: 'Connected and archiving automatically', status: 'have' },
            { name: 'UPS battery backup', note: 'Camera + controller survive a power blink', status: 'later', price: '$70' },
            {
                name: 'Long-range barcode scanner',
                note: 'Only if the jumbo box labels disappoint at 4–6 ft with a normal gun',
                status: 'later',
                price: '$450',
            },
        ],
    },
];

const totalNeed = '≈ $1,650';
</script>

<template>
    <Head title="Equipment" />
    <div class="mx-auto flex min-h-screen w-full max-w-2xl flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">🛒 Equipment</h1>
            <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            The floor, station by station — what we have, what's on the way, and what's still to buy.
            Prices are ballpark; B&amp;H and Amazon leapfrog each other weekly.
        </p>

        <div class="mt-4 flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-800">Still to buy, everything included</p>
            <p class="text-xl font-bold text-amber-900">{{ totalNeed }}</p>
        </div>

        <div v-for="station in STATIONS" :key="station.name" class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm">
            <img :src="station.photo" :alt="station.name" class="h-44 w-full object-cover" loading="lazy" />
            <div class="p-4">
                <h2 class="text-lg font-bold text-gray-900">{{ station.emoji }} {{ station.name }}</h2>
                <p class="mt-0.5 text-xs text-gray-500">{{ station.blurb }}</p>

                <div class="mt-3 divide-y divide-gray-100">
                    <div v-for="item in station.items" :key="item.name" class="flex items-start justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">
                                <span
                                    class="mr-1.5 inline-block rounded px-1.5 py-0.5 text-xs font-bold"
                                    :class="BADGES[item.status].classes"
                                >{{ BADGES[item.status].label }}</span>
                                {{ item.name }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ item.note }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p v-if="item.price" class="text-sm font-bold text-gray-900">{{ item.price }}</p>
                            <p v-if="item.links" class="mt-0.5 space-x-2 text-xs">
                                <a
                                    v-for="link in item.links"
                                    :key="link.url"
                                    :href="link.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-semibold text-blue-600 hover:underline"
                                >{{ link.label }} ↗</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-gray-400">
            Photos are AI-generated visualizations of the planned floor.
        </p>
    </div>
</template>
