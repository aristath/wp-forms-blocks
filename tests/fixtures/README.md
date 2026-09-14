# Gutenberg serialization fixtures

`blocks/` is the complete form-block fixture corpus copied byte-for-byte from the official Gutenberg `v23.9.1` tag (`c29617a19a0197efdf3f53a820833c2d68c0b405`):

- 15 fixture cases
- 4 files per case: input HTML, parser JSON, block JSON, and expected serialized HTML
- 60 files total

The corpus covers all four blocks plus every form and input deprecation present in that release. `tests/js/serialization.test.js` checks the corpus count, parser output, block migration output, and exact reserialization.
