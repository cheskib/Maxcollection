<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    item: {
        id: number;
        title: string;
        category: string;
        values: Record<string, string | null>;
        images: { id: number; original_filename: string; version: string }[];
    };
    categoryFields: Record<string, string[]>;
    fieldLabels: Record<string, string>;
}>();

const categories = [
    { value: 'sports_card', label: 'Sports Card' },
    { value: 'comic_book', label: 'Comic Book' },
    { value: 'coin', label: 'Coin' },
    { value: 'stamp', label: 'Stamp' },
    { value: 'unsupported', label: 'Unsupported' },
];

const SPORTS = ['Baseball', 'Basketball', 'Football', 'Hockey', 'Soccer', 'Golf', 'Tennis', 'Boxing', 'Wrestling', 'Racing', 'Other'];
const YES_NO = ['Yes', 'No'];

// Team suggestions per sport. The team field stays free-typed underneath so
// defunct, minor-league, and college teams remain enterable.
const TEAMS: Record<string, string[]> = {
    Baseball: [
        'Arizona Diamondbacks', 'Athletics', 'Atlanta Braves', 'Baltimore Orioles', 'Boston Red Sox',
        'Chicago Cubs', 'Chicago White Sox', 'Cincinnati Reds', 'Cleveland Guardians', 'Colorado Rockies',
        'Detroit Tigers', 'Houston Astros', 'Kansas City Royals', 'Los Angeles Angels', 'Los Angeles Dodgers',
        'Miami Marlins', 'Milwaukee Brewers', 'Minnesota Twins', 'New York Mets', 'New York Yankees',
        'Philadelphia Phillies', 'Pittsburgh Pirates', 'San Diego Padres', 'San Francisco Giants',
        'Seattle Mariners', 'St. Louis Cardinals', 'Tampa Bay Rays', 'Texas Rangers', 'Toronto Blue Jays',
        'Washington Nationals',
    ],
    Basketball: [
        'Atlanta Hawks', 'Boston Celtics', 'Brooklyn Nets', 'Charlotte Hornets', 'Chicago Bulls',
        'Cleveland Cavaliers', 'Dallas Mavericks', 'Denver Nuggets', 'Detroit Pistons', 'Golden State Warriors',
        'Houston Rockets', 'Indiana Pacers', 'LA Clippers', 'Los Angeles Lakers', 'Memphis Grizzlies',
        'Miami Heat', 'Milwaukee Bucks', 'Minnesota Timberwolves', 'New Orleans Pelicans', 'New York Knicks',
        'Oklahoma City Thunder', 'Orlando Magic', 'Philadelphia 76ers', 'Phoenix Suns', 'Portland Trail Blazers',
        'Sacramento Kings', 'San Antonio Spurs', 'Toronto Raptors', 'Utah Jazz', 'Washington Wizards',
    ],
    Football: [
        'Arizona Cardinals', 'Atlanta Falcons', 'Baltimore Ravens', 'Buffalo Bills', 'Carolina Panthers',
        'Chicago Bears', 'Cincinnati Bengals', 'Cleveland Browns', 'Dallas Cowboys', 'Denver Broncos',
        'Detroit Lions', 'Green Bay Packers', 'Houston Texans', 'Indianapolis Colts', 'Jacksonville Jaguars',
        'Kansas City Chiefs', 'Las Vegas Raiders', 'Los Angeles Chargers', 'Los Angeles Rams', 'Miami Dolphins',
        'Minnesota Vikings', 'New England Patriots', 'New Orleans Saints', 'New York Giants', 'New York Jets',
        'Philadelphia Eagles', 'Pittsburgh Steelers', 'San Francisco 49ers', 'Seattle Seahawks',
        'Tampa Bay Buccaneers', 'Tennessee Titans', 'Washington Commanders',
    ],
    Hockey: [
        'Anaheim Ducks', 'Boston Bruins', 'Buffalo Sabres', 'Calgary Flames', 'Carolina Hurricanes',
        'Chicago Blackhawks', 'Colorado Avalanche', 'Columbus Blue Jackets', 'Dallas Stars', 'Detroit Red Wings',
        'Edmonton Oilers', 'Florida Panthers', 'Los Angeles Kings', 'Minnesota Wild', 'Montreal Canadiens',
        'Nashville Predators', 'New Jersey Devils', 'New York Islanders', 'New York Rangers', 'Ottawa Senators',
        'Philadelphia Flyers', 'Pittsburgh Penguins', 'San Jose Sharks', 'Seattle Kraken', 'St. Louis Blues',
        'Tampa Bay Lightning', 'Toronto Maple Leafs', 'Utah Mammoth', 'Vancouver Canucks', 'Vegas Golden Knights',
        'Washington Capitals', 'Winnipeg Jets',
    ],
};

const MANUFACTURERS = [
    'Topps', 'Bowman', 'Panini', 'Upper Deck', 'Fleer', 'Donruss', 'Score',
    'Leaf', 'O-Pee-Chee', 'Pinnacle', 'Skybox', 'Pacific', 'Pro Set',
];

// Card type is a dropdown; a value the AI wrote that is not listed here
// stays selectable via optionsFor's keep-current rule.
const CARD_TYPES = [
    'Base', 'All-Star', 'Team Leaders', 'League Leaders', 'Record Breaker',
    'Highlights', 'Turn Back the Clock', 'Reprint', 'Rookie Subset',
    'Future Stars', 'Checklist', 'Traded', 'Insert',
];

// Original Card Year only makes sense on reprint-style cards.
const REPRINT_TYPES = ['reprint', 'turn back the clock', 'retro'];

// Sports cards effectively start around 1900; coins and stamps can be far
// older, so the year dropdown applies to sports cards only.
const YEARS = Array.from({ length: new Date().getFullYear() - 1899 }, (_, i) => String(new Date().getFullYear() - i));

const teamSuggestions = computed<string[] | null>(() => {
    const sport = (form as Record<string, any>).sport as string | null;
    return sport ? (TEAMS[sport] ?? null) : null;
});

// Keep whatever value is already stored selectable, even if it is not in
// the standard list, so opening the editor never silently changes data.
function optionsFor(field: string): string[] | null {
    const base =
        field === 'sport' ? SPORTS
        : field === 'card_type' ? CARD_TYPES
        : field === 'rookie_card' || field === 'autograph' ? YES_NO
        : (field === 'year' || field === 'original_year') && form.category === 'sports_card' ? YEARS
        : null;
    if (!base) return null;
    const current = (form as Record<string, any>)[field];
    return current && !base.includes(current) ? [current, ...base] : base;
}

const form = useForm({
    category: props.item.category,
    ...props.item.values,
});

// Only the fields belonging to the selected category are shown and saved;
// what appears also adapts to the card type (original year is only
// meaningful on reprint-style cards).
const visibleFields = computed(() => {
    const fields = props.categoryFields[form.category] ?? [];
    const cardType = ((form as Record<string, any>).card_type ?? '').toLowerCase();
    const isReprint = REPRINT_TYPES.includes(cardType);
    return fields.filter((field) => field !== 'original_year' || isReprint);
});

function submit(): void {
    form.put(`/items/${props.item.id}/metadata`);
}

function rotateImage(imageId: number): void {
    router.post(`/images/${imageId}/rotate`, {}, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <Head :title="`Edit ${item.title}`" />
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 py-8">
        <div class="flex items-center justify-between">
            <h1 class="min-w-0 truncate text-xl font-bold text-gray-900">Edit Item</h1>
            <Link :href="`/items/${item.id}`" class="ml-3 shrink-0 text-sm font-semibold text-blue-600">Cancel</Link>
        </div>

<!-- The photographs stay visible while editing: corrections are made by
     reading the item itself. Tapping opens the full-size original. -->
        <div v-if="item.images.length" class="mt-4 grid grid-cols-2 gap-3">
            <div v-for="image in item.images" :key="image.id" class="relative">
                <a :href="`/images/${image.id}`" target="_blank">
                    <img
                        :src="`/thumbnails/${image.id}?v=${image.version}`"
                        :alt="image.original_filename"
                        class="w-full rounded-xl bg-gray-100 object-contain shadow-sm"
                    />
                </a>
                <button
                    class="absolute right-2 top-2 rounded-lg bg-gray-900/70 px-2 py-1 text-sm font-semibold text-white hover:bg-gray-900"
                    title="Rotate a quarter turn"
                    type="button"
                    @click="rotateImage(image.id)"
                >
                    ↻
                </button>
            </div>
        </div>

        <form class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                <select
                    id="category"
                    v-model="form.category"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                >
                    <option v-for="category in categories" :key="category.value" :value="category.value">
                        {{ category.label }}
                    </option>
                </select>
            </div>

            <div v-for="field in visibleFields" :key="field">
                <label :for="field" class="block text-sm font-medium text-gray-700">{{ fieldLabels[field] }}</label>
                <textarea
                    v-if="field === 'condition_notes'"
                    :id="field"
                    v-model="(form as Record<string, any>)[field]"
                    rows="3"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                ></textarea>
                <select
                    v-else-if="optionsFor(field)"
                    :id="field"
                    v-model="(form as Record<string, any>)[field]"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                >
                    <option :value="null">—</option>
                    <option v-for="option in optionsFor(field)" :key="option" :value="option">{{ option }}</option>
                </select>
                <template v-else-if="field === 'team' && teamSuggestions">
                    <input
                        :id="field"
                        v-model="(form as Record<string, any>)[field]"
                        type="text"
                        list="team-suggestions"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                    />
                    <datalist id="team-suggestions">
                        <option v-for="team in teamSuggestions" :key="team" :value="team" />
                    </datalist>
                </template>
                <template v-else-if="field === 'manufacturer'">
                    <input
                        :id="field"
                        v-model="(form as Record<string, any>)[field]"
                        type="text"
                        list="manufacturer-suggestions"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                    />
                    <datalist id="manufacturer-suggestions">
                        <option v-for="maker in MANUFACTURERS" :key="maker" :value="maker" />
                    </datalist>
                </template>
                <input
                    v-else
                    :id="field"
                    v-model="(form as Record<string, any>)[field]"
                    type="text"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                />
                <p v-if="(form.errors as Record<string, string>)[field]" class="mt-1 text-sm text-red-600">
                    {{ (form.errors as Record<string, string>)[field] }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-blue-600 py-3 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
            >
                Save
            </button>
        </form>
    </div>
</template>
