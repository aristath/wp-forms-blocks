# Gutenberg serialization fixtures

`blocks/` contains every current-format form-block fixture copied byte-for-byte from the official Gutenberg `v23.9.1` tag (`c29617a19a0197efdf3f53a820833c2d68c0b405`):

- 7 fixture cases
- 4 files per case: input HTML, parser JSON, block JSON, and expected serialized HTML
- 28 files total

The corpus covers all four blocks and the current input variations. Historical deprecated fixtures were deliberately removed together with the migration implementations. `tests/js/serialization.test.js` checks the corpus count, parser output, block output, and exact reserialization.
