# Code Comments

- Never write code comments of any kind: no inline comments, block comments, prose DocBlocks or TODOs.
- This applies to PHP, Vue, TypeScript, JavaScript, CSS, Blade, migrations, config and tests.
- Express intent through clear names for classes, methods and variables, and through small focused methods.
- Use native PHP type hints and return types first.
- The only exception is a type-only PHPDoc that PHPStan needs and native PHP cannot express: `@param`, `@return`, `@var`, `@use`, `@extends`, `@mixin` with array shapes, list types or generics. Never add descriptions or prose to these blocks.
- When editing a file that already has comments, remove them, keeping only type-only PHPDoc.
