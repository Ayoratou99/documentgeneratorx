# Changelog

All notable changes to this project are documented in this file.

## [2.0.9] - 2026-09-30

### Fixed
- **LibreOffice conversion failing silently in some Docker environments**
  ("LibreOffice conversion failed: " with no output). Each conversion now runs
  with its own isolated user profile (`-env:UserInstallation`), a writable
  `HOME` and a unique output directory, so it no longer breaks when the PHP
  user's home is not writable or when several requests/queue workers convert
  concurrently. Exit code 81 (profile initialisation) is retried once.
- Command arguments are now shell-escaped; the PDF move falls back to copy
  when crossing filesystems/volumes.

### Changed
- LibreOffice error messages now include the exit code, the system user and
  the executed command to make production failures diagnosable.

## [2.0.8] - 2026-09-09

### Added
- **Conditional blocks** (`{{if:…}}` / `{{elseif:…}}` / `{{else}}` / `{{endif}}`)
  that show or hide parts of the document based on a variable. Conditions support
  truthy checks (`{{if:flag}}`), equality (`{{if:age=A}}`, `==` too) and inequality
  (`{{if:age!=A}}`); the right-hand side is a literal, optionally quoted, string and
  comparison is case-sensitive. Works both **block** (markers alone in their
  paragraphs — whole paragraphs/tables kept or dropped with no blank lines left)
  and **inline** (markers within a line of text), and conditionals may be nested.
  Placeholders inside a kept branch are still replaced normally.

### Tests
- Unit test `ConditionalProcessorTest` (inline, block, elseif/else, nesting,
  operators, truthiness) and feature test `DocxConditionalTest` on a real `.docx`.

## [2.0.7] - 2026-06-08

### Added
- **`array` variable type** (`{{name:array}}`) for repeating table rows.
  A list of values fills a table column top-to-bottom: the row holding the
  placeholder is cloned once per value, blank rows drawn beneath it are reused
  first, and extra rows are added (or surplus blank rows removed) so the table
  always matches the data length. Several `array` columns in the same row
  expand together to the longest list, padding shorter columns with blank cells.
  An `array` placeholder outside a table renders its values on separate lines.
  Values are XML-escaped and accept the same inline styles as text.

### Tests
- Unit tests `ArrayProcessorTest` and `VariableParserArrayTest`.
- Real-`.docx` feature test `DocxArrayExpansionTest` covering the full
  fragment-repair -> array-expansion -> replacement pipeline.
- Runnable example `examples/array_table_demo.php`.