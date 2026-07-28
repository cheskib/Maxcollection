# Wishlist

Ideas approved for the wishlist by the owner — not scheduled, not built.
Each entry notes where it came from so context isn't lost.

## Duplicate-card awareness

The app could notice "these look like the same card" and show "×3 owned"
on the set page — the way collectors keep trade lists ("I have 2 spare
Mattinglys"). Duplicates are fully allowed today; this would turn them
from coexisting items into a counted feature.
*(Owner added 2026-07-28, following the batch-guardrail discussion.)*

## Live market value data

Replace or inform the AI Ballpark with real sold-price data (eBay sold
listings, PriceCharting-style services). Needs an external data source
with credentials plus card-matching logic. Until then, value is two
ranges: Our Value (manual, authoritative) and AI Ballpark (rough,
refreshed on each processing run).
*(Owner decision 2026-07-28 when card value was added.)*

## Re-enable the Sets catalog

The Sets catalog (self-building set profiles with AI design histories)
is hidden — owner decision, "get rid of sets for now… or maybe just
hide it." Profiles still accrue silently; descriptions are not written
while hidden. To re-enable: restore the Home tile, the summary's By
Set section, and the DescribeSetJob dispatch in
ProcessingService::registerCardSet.
*(2026-07-28.)*

## Other items deferred in discussion

- **Set completion tracking** — "1987 Topps has 792 cards, you own 14"
  on each set page. *(Raised when the Sets catalog was designed.)*
- **User-added categories** — generic field set, managed from Settings.
  Owner decision: "leave it as is for now". *(Category review.)*
- **Card-type value cleanup** — normalize AI-written variants
  ("All-Star" vs "All Star", "League Leaders" vs "League Leader").
  *(Noticed on the Processed Items overview.)*
- **Search index at scale** — free-text search currently scans; fine at
  thousands of cards, will want a proper index around six figures.
  *(300,000-card target discussion.)*
- **Storage growth** — 300k cards × ~2 scans is real disk; the Railway
  volume will need enlarging as the collection grows. *(Same.)*
