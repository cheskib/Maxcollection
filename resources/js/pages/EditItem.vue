<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

// Original Card Year only makes sense on reprint-style cards, and a
// checklist has no player or team.
const REPRINT_TYPES = ['reprint', 'turn back the clock', 'retro'];
const NO_PLAYER_TYPES = ['checklist'];

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

const page = usePage<{ flash: { status: string | null } }>();

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
    const hasPlayer = !NO_PLAYER_TYPES.includes(cardType);
    return fields.filter(
        (field) =>
            (field !== 'original_year' || isReprint) &&
            (!['player_name', 'team'].includes(field) || hasPlayer),
    );
});

// Compact bottom section: rookie + autograph share a row, and the three
// rarely-used fields hide behind an "Add …" link unless already filled.
const PAIR_FIELDS = ['rookie_card', 'autograph'];
const MISC_FIELDS = ['set_name', 'parallel', 'serial_number'];

const mainFields = computed(() => visibleFields.value.filter((field) => !PAIR_FIELDS.includes(field) && !MISC_FIELDS.includes(field)));
const pairFields = computed(() => visibleFields.value.filter((field) => PAIR_FIELDS.includes(field)));
const miscFields = computed(() => visibleFields.value.filter((field) => MISC_FIELDS.includes(field)));

const showMisc = ref(
    Boolean(props.item.values.set_name || props.item.values.parallel || props.item.values.serial_number),
);

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
            <div class="ml-3 flex shrink-0 gap-4">
                <Link href="/" class="text-sm font-semibold text-blue-600">Home</Link>
                <Link :href="`/items/${item.id}`" class="text-sm font-semibold text-blue-600">Back</Link>
            </div>
        </div>

        <p v-if="page.props.flash.status" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            ✓ {{ page.props.flash.status }}
        </p>

<!-- The photographs stay pinned while the fields scroll underneath:
     corrections are made by reading the item itself. Tapping opens the
     full-size original. -->
        <div v-if="item.images.length" class="sticky top-0 z-10 -mx-4 bg-gray-100 px-4 pb-3 pt-2 shadow-sm">
            <div class="grid grid-cols-2 gap-3">
                <div v-for="image in item.images" :key="image.id" class="relative">
                    <a :href="`/images/${image.id}`" target="_blank">
                        <img
                            :src="`/thumbnails/${image.id}?v=${image.version}`"
                            :alt="image.original_filename"
                            class="max-h-44 w-full rounded-xl bg-gray-100 object-contain shadow-sm"
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

            <div v-for="field in mainFields" :key="field">
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

            <div v-if="pairFields.length" class="grid grid-cols-2 gap-3">
                <div v-for="field in pairFields" :key="field">
                    <label :for="field" class="block text-sm font-medium text-gray-700">{{ fieldLabels[field] }}</label>
                    <select
                        :id="field"
                        v-model="(form as Record<string, any>)[field]"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                    >
                        <option :value="null">—</option>
                        <option v-for="option in optionsFor(field)" :key="option" :value="option">{{ option }}</option>
                    </select>
                </div>
            </div>

            <template v-if="miscFields.length">
                <button
                    v-if="!showMisc"
                    type="button"
                    class="text-left text-sm font-semibold text-blue-600 hover:text-blue-700"
                    @click="showMisc = true"
                >
                    ＋ Add Set Name, Parallel, and Serial #
                </button>
                <div v-else class="flex flex-col gap-4">
                    <div v-for="field in miscFields" :key="field">
                        <label :for="field" class="block text-sm font-medium text-gray-700">{{ fieldLabels[field] }}</label>
                        <input
                            :id="field"
                            v-model="(form as Record<string, any>)[field]"
                            type="text"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5"
                        />
                    </div>
                </div>
            </template>

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
