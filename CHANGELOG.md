# Changelog

## v4.0.10 - 2026-11-20

### Description
Correct the plugin version metadata used by WordPress after updates.

### Type
Patch

### Notes
- Set the plugin header and runtime version to v4.0.10 so WordPress no longer repeats the same update offer.

## v4.0.9 - 2026-11-20

### Description
Match the Actions column's right inset to the other table columns.

### Type
Patch

### Notes
- Add the same right padding as the first column while keeping the Actions heading aligned with its controls.

## v4.0.8 - 2026-11-20

### Description
Tighten the Actions column and simplify CDS_IDS editing.

### Type
Patch

### Notes
- Reduce empty space in the Actions column and increase the gap between controls.
- Use a single-line comma-separated CDS_IDS editor with the requested optional label.
- Preserve compact exam rows and the responsive shortcode field layout.


## v4.0.7 - 2026-11-20

### Description
Align admin table controls, row spacing, and shortcode builder fields.

### Type
Patch

### Notes
- Align the Actions heading with the left edge of its action buttons.
- Give CDS_IDS more table width and compact its line height so normal rows match the degree-session table.
- Fill the shortcode builder row with six equal fields, then reflow based on container width.


## v4.0.6 - 2026-11-20

### Description
Align the admin tables and make the shortcode builder fit available width.

### Type
Patch

### Notes
- Center the exam Actions heading and controls in the same responsive column.
- Match exam-table row spacing to the degree-session table while allowing course codes to wrap only between IDs.
- Use a fluid shortcode-builder grid so fields share a row whenever the viewport allows.

## v4.0.5 - 2026-11-20

### Description
Preserve Calendari's active state after WordPress completes a plugin update.

### Type
Patch

### Notes
- Record whether this plugin was active before its update begins.
- Restore activation after a successful update only when it was active beforehand.
- Preserve network activation state on multisite installations.

## v4.0.4 - 2026-11-20

### Description
Expand the admin workspace fluidly and make course-code lists wrap naturally.

### Type
Patch

### Notes
- Use the available WordPress admin content width instead of a fixed maximum.
- Let backend forms, filters, tables, and dialogs adapt to the expanded space.
- Keep each CDS ID intact and wrap the list between IDs only when needed.

## v4.0.3 - 2026-11-19

### Description
Expose automatic-update controls and refresh GitHub release data on demand.

### Type
Patch

### Notes
- Report update support to WordPress even when the installed version is already current.
- Bypass the cached GitHub release response during a forced update check.

## v4.0.2 - 2026-11-19

### Description
Wrap course-code lists in the exam database editor and table.

### Type
Patch

### Notes
- Display each `CDS_IDS` code on a separate line in the admin database.
- Use a multiline field for editing course codes while preserving comma-separated storage and imports.

## v4.0.1 - 2026-11-19

### Description
Fix automatic update matching for GitHub release asset names.

### Type
Patch

### Notes
- Accept GitHub's period-normalized release ZIP name as well as names containing spaces.

## v4.0.0 - 2026-11-19

### Description
Return to event-level course assignments and simplify grouped calendar output.

### Type
Major

### Notes
- Store comma-separated course IDs directly on each exam event row and import them with the event.
- Keep the group name optional; render one table per named group while preserving course-code pills and omitting an ungrouped heading.
- Add secure one-click updates from public GitHub Releases without embedding credentials.
- Migrate existing course assignments into event rows without discarding calendar records.

## v3.0.1 - 2026-10-16

### Description
Complete flexible course-group management and screenshot calendar population.

### Type
Patch

### Notes
- Store course IDs in group-membership and event-history relations, not in schedule rows.
- Add editable groups, nullable assignments, effective-dated membership changes, audit history, and ungrouped calendar sections.
- Add the new `GRUPPO` event import format, optional Project Work deadlines, and Italian group notes.
- Seed the 2026/2027 CP, CO, Master, and ungrouped CDS calendars from the supplied screenshots.

## v3.0.0 - 2026-10-16

### Description
Replace per-event course codes with configurable course groups and rebuild the 2026/2027 exam calendar data.

### Type
Major

### Notes
- Remove `CDS_ID` from exam event records and use nullable groups with separate, academic-year-scoped course membership and assignment history.
- Add Project Work deadlines and group-level public notes for CLIL cohorts.
- Replace the legacy event CSV format with `GRUPPO`; manage course membership separately.
- Reset exam events and group mappings on the v3 upgrade, retain degree-session records and appearance settings, and seed the screenshot calendar dataset.
- Keep oral/written exam types specific to CDS exam events.

## v2.13.2 - 2026-10-16

### Description
Dismiss transient admin notifications automatically.

### Type
Patch

### Notes
- Automatically dismiss action-result notices and informational builder toasts after five seconds.
- Keep builder validation and error alerts visible until the user corrects or clears them.

## v2.13.1 - 2026-10-16

### Description
Replace the booking-date checkbox with a styled visibility dropdown.

### Type
Patch

### Notes
- Add a `Date prenotazioni` selector with `Mostra` as the default and `Nascondi` as the option to show `DA DEFINIRE` publicly.
- Keep the selection synchronized when opening existing exam records for editing.

## v2.13.0 - 2026-10-12

### Description
Add public visibility controls for calendar records and booking dates.

### Type
Minor

### Notes
- Add eye/eye-off actions to hide or restore exam and degree-session records in public calendars while keeping them in admin.
- Add one exam setting to show both booking dates as `DA DEFINIRE` in public table and grid views.
- Preserve visibility settings when importing updates to existing records.

## v2.12.4 - 2026-10-12

### Description
Restrict Tabbed View to the All exam-type filter in the builder.

### Type
Patch

### Notes
- Disable Tabbed View when `TIPO PROVA` is set to Oral or Written, and re-enable it for All.
- Reset the builder preview to Table if a specific exam type is selected while Tabbed View is active.
- Preserve direct shortcode behavior and the existing public tab rendering.

## v2.12.3 - 2026-10-12

### Description
Preserve the selected tab styling in the shortcode preview.

### Type
Patch

### Notes
- Override admin preview hover and focus styles for the selected tab so its accent fill remains visible.
- Keep the public shortcode tab styling unchanged.

## v2.12.2 - 2026-10-12

### Description
Keep the selected tab clearly visible in the shortcode preview.

### Type
Patch

### Notes
- Preserve the active accent fill, border, shadow, and contrast text over admin preview hover and focus styles.
- Leave the public shortcode tab appearance unchanged.

## v2.12.1 - 2026-10-12

### Description
Refine tab controls to match the configured calendar styling.

### Type
Patch

### Notes
- Make the written/oral tabs two equal-width floating controls without the underline.
- Use the configured table radius and existing accent palette for tab surfaces and active state.

## v2.12.0 - 2026-10-12

### Description
Add a tabbed exam-calendar view with separate written and oral tables.

### Type
Minor

### Notes
- Add the `view="tabs"` shortcode view and expose it as `Tabbed View` in the builder for exam calendars.
- Render separate `Prova Scritta` and `Prova Orale` table tabs with keyboard-accessible tab navigation.
- Preserve existing table, grid, combined, and degree-session views.

## v2.11.5 - 2026-10-12

### Description
Keep missing-field highlights neutral until the user attempts to generate a preview.

### Type
Patch

### Notes
- Do not render invalid field classes or ARIA flags from a stale preview query on page load.
- Apply danger styling only from the current preview submission, and clear each field as its required value is selected.

## v2.11.4 - 2026-10-12

### Description
Apply missing-field danger styling only after a preview attempt.

### Type
Patch

### Notes
- Keep fields visually neutral on initial builder load, including pages with a stale preview query.
- Apply `aria-invalid` and danger styling from the current client-side preview attempt, then clear completed fields as selections change.

## v2.11.3 - 2026-10-12

### Description
Highlight missing shortcode builder fields with accessible danger feedback.

### Type
Patch

### Notes
- Mark missing required fields for exam and degree-session previews with a subtle red border and label treatment.
- Identify invalid fields with `aria-invalid`, focus the first missing field, and show a danger toast.
- Clear the missing-field state as the required selections are completed.

## v2.11.2 - 2026-10-12

### Description
Use the existing toast presentation for shortcode builder AJAX feedback.

### Type
Patch

### Notes
- Show builder option updates, empty states, and AJAX errors through the established top-right toast styling.
- Keep loading feedback accessible with a screen-reader status and form busy state.

## v2.11.1 - 2026-10-12

### Description
Prevent duplicate plugin instances from colliding during ZIP-based updates.

### Type
Patch

### Notes
- Skip loading additional Calendari copies and show an admin notice to deactivate duplicates.
- Preserve the no-reload, AJAX-backed shortcode builder introduced in v2.11.0.

## v2.11.0 - 2026-10-09

### Description
Make shortcode builder selections responsive and require an explicit calendar type.

### Type
Minor

### Notes
- Start with no calendar type selected and keep dependent controls disabled until a type is chosen.
- Load academic years and course types through a nonce-protected AJAX endpoint without reloading the admin page.
- Clear stale shortcode and preview output when builder selections change.

## v2.10.3 - 2026-10-07

### Description
Let database columns size to their content while keeping the Actions column fixed.

### Type
Patch

### Notes
- Use automatic table layout for both database tables so long and short fields receive content-appropriate widths.
- Keep the Actions column fixed at 112px for consistent icon placement.

## v2.10.2 - 2026-10-07

### Description
Keep the actions column fixed-width and distribute remaining database table width across data columns.

### Type
Patch

### Notes
- Apply consistent fixed-width Actions columns to the degree-session and exam database tables.
- Use fixed table layout so all remaining columns share the available width.

## v2.10.1 - 2026-10-07

### Description
Fix exam edit actions after AJAX filtering and show date-derived month/year values.

### Type
Patch

### Notes
- Display `DATA_ESAMI` as month and year in both the exam database and public exam calendar.
- Delegate degree-session and exam edit clicks so replaced AJAX rows remain editable.

## v2.10.0 - 2026-10-07

### Description
Add modal-based exam-call management and derive exam months from DATA_ESAMI.

### Type
Minor

### Notes
- Add shared create/edit modals and icon-only row actions to the exam database.
- Derive and store `mese` from `DATA_ESAMI` on admin saves, imports, and database writes.
- Derive exam month display and month filters directly from `DATA_ESAMI`.
- Continue accepting legacy import files containing `MESE`, but ignore the supplied month; update the sample CSV to omit it.
- Use pointer cursors for icon actions.

## v2.9.0 - 2026-10-07

### Description
Apply both database filters and pagination asynchronously without full-page reloads.

### Type
Minor

### Notes
- Update results over a nonce-protected admin AJAX endpoint when filters change or are cleared.
- Preserve the active filter state, result count, pagination, and accessible loading/error feedback.
- Keep the clear-filters icon disabled until filters are active.

## v2.8.2 - 2026-10-07

### Description
Apply database filters automatically and make the compact filter icon clear active filters.

### Type
Patch

### Notes
- Apply a filter immediately when an option is selected.
- Keep the filter icon disabled when no filters are active; enable it to clear all filters.
- Let filter fields flex to the available width and retain the compact icon-button footprint.

## v2.8.1 - 2026-10-07

### Description
Compact the degree-session filter, distinguish delete actions, and show the date-derived year beside the month.

### Type
Patch

### Notes
- Replace the filter text button with an accessible Tabler filter icon button.
- Use red styling and matching hover/focus states for delete icon actions.
- Display month and year from each session date in the degree-session database table.

## v2.8.0 - 2026-10-07

### Description
Redesign degree-session entry and row actions around a shared create/edit modal.

### Type
Minor

### Notes
- Replace the inline add row and record-count badge with a “Nuova Sessione” button.
- Use one modal for creating and editing sessions, with AA_ID, DATA, and SEDE fields.
- Replace visible edit/delete labels with accessible icon-only row actions.
- Load the pinned Tabler Icons webfont from jsDelivr for modal and action icons.

## v2.7.8 - 2026-10-07

### Description
Remove the unnecessary existing-row month backfill while retaining date-derived values for new saves.

### Type
Patch

### Notes
- Remove the startup scan and rewrite of stored degree-session month values.
- Keep deriving the stored month from the date on insert and update, and keep date-based output.

## v2.7.7 - 2026-10-07

### Description
Continue normalizing valid degree-session months when legacy rows contain invalid dates.

### Type
Patch

### Notes
- Skip and log invalid legacy dates instead of aborting the remaining month backfill.
- Advance the versioned migration marker only after all valid rows have been synchronized.

## v2.7.6 - 2026-10-07

### Description
Make the one-time degree-session month migration update each stored row through the date parser.

### Type
Patch

### Notes
- Derive each existing month from its date and persist mismatches row by row.
- Advance the independent migration marker only after every record is synchronized.

## v2.7.5 - 2026-10-07

### Description
Ensure the degree-session month backfill runs independently of the general database version setting.

### Type
Patch

### Notes
- Track the date-to-month backfill with its own one-time migration option and retry it until successful.
- Preserve date-derived month values for new and edited degree sessions.

## v2.7.4 - 2026-10-07

### Description
Made the session date the single source for degree-session month and year values.

### Type
Patch

### Notes
- Removed manual month entry from degree-session creation and editing.
- Derive and persist the month from the date on every degree-session insert and update.
- Normalize existing stored degree-session months from their dates during the database upgrade.
- Derive degree-session month and year labels directly from the saved date.

## v2.7.3 - 2026-10-07

### Description
Capitalized degree-session month-year labels and made exam-only builder fields react to calendar type changes.

### Type
Patch

### Notes
- Capitalized the month in degree-session table labels while continuing to derive the year from `DATA`.
- Disabled and greyed exam-only shortcode fields immediately when degree sessions are selected.
- Refreshed the academic-year choices when switching between exam and degree-session calendars.

## v2.7.2 - 2026-10-02

### Description
Clarified degree-session shortcode settings and month-year output.

### Type
Patch

### Notes
- Kept exam-only shortcode controls visible but disabled for degree-session calendars.
- Added the year from each session's date beside its month in the generated degree-session table.

## v2.7.1 - 2026-10-02

### Description
Fixed shortcode initialization for degree-session calendars.

### Type
Patch

### Notes
- Passed the degree-session database dependency to the shortcode renderer during plugin startup.
- Prevented the shortcode constructor argument mismatch that caused a WordPress boot error.

## v2.7.0 - 2026-10-02

### Description
Added degree-session calendars to the shortcode builder and public renderer.

### Type
Minor

### Notes
- Added degree-session academic years to the shortcode builder.
- Generated degree-session shortcodes with `tipologia="laurea"`, `aa_id`, and the selected view.
- Rendered degree sessions in table, grid, or combined views using month, date, and venue data.
- Preserved existing exam shortcode syntax and behavior.

## v2.6.3 - 2026-10-02

### Description
Moved all plugin-generated admin notices into top-right toasts.

### Type
Patch

### Notes
- Applied fixed toast positioning to shortcode validation and information/warning notices.
- Converted missing-record errors to toasts so they do not shift page content.
- Kept all save, import, update, and delete status notices in the same toast layer.

## v2.6.2 - 2026-10-02

### Description
Moved admin action notifications into a fixed top-right toast.

### Type
Patch

### Notes
- Positioned status notices over the admin interface so they no longer shift cards or tables.
- Styled the toast to match the existing admin palette and rounded card treatment.
- Adjusted toast placement for the WordPress mobile admin toolbar.

## v2.6.1 - 2026-10-02

### Description
Reworked the degree-session admin page into a filtered table with inline entry.

### Type
Patch

### Notes
- Added searchable, database-backed filters for academic year, month, date, and venue.
- Replaced the always-visible entry form with an empty first table row and a `Salva` action.
- Kept editing and deletion available for saved degree sessions.

## v2.6.0 - 2026-10-02

### Description
Added a separate backend database for degree sessions.

### Type
Minor

### Notes
- Added manual degree-session records with academic year, month, date, and venue fields.
- Added backend listing, editing, and deletion for degree-session records.
- Kept degree-session data in its own WordPress table, separate from exam records.
- Added automatic table installation and version-based upgrades.

## v2.5.3 - 2026-10-02

### Description
Removed the duplicate dropdown arrow and enforced pointer cursors.

### Type
Patch

### Notes
- Draws a single CSS chevron on each dropdown trigger, without a separate arrow glyph.
- Forces pointer cursors on dropdown triggers and selectable options.

## v2.5.2 - 2026-10-02

### Description
Removed the duplicate dropdown arrow and restored pointer cursors.

### Type
Patch

### Notes
- Removed the text arrow that duplicated the CSS chevron.
- Added pointer cursors to dropdown triggers and selectable options.

## v2.5.1 - 2026-10-02

### Description
Rounded the repository dropdown controls to match the existing admin design.

### Type
Patch

### Notes
- Rounded dropdown triggers and option rows to match the existing form-control styling.
- Matched the open dropdown panel radius and shadow to the admin card design.
- Added a consistent focus-visible outline to dropdown triggers.

## v2.5.0 - 2026-10-02

### Description
Redesigned exam repository filters as single searchable dropdowns.

### Type
Minor

### Notes
- Replaced the separate search boxes and select controls with one dropdown trigger per filter.
- Added an in-dropdown search field and selectable database-backed options for academic year, course type, course code, exam type, and month.
- Kept full Italian month names in the repository table and month filter.
- Preserved exact filtering, pagination, and the selected repository tab.

## v2.4.0 - 2026-10-02

### Description
Added searchable, database-backed filters to the exam repository.

### Type
Minor

### Notes
- Replaced the numeric month values in the admin repository with full Italian month names.
- Added searchable dropdown filters populated from stored academic years, course types, course codes, exam types, and months.
- Applied exact-value filtering and preserved the selected repository tab when submitting filters.
- Added an option to filter records with no exam type when such records exist.

## v2.3.3 - 2026-10-02

### Description
Updated calendar and admin labels for consistency.

### Type
Patch

### Notes
- Renamed reservation-window labels to `Prenotazioni` in the public table and grid.
- Replaced visible `Codice breve` labels with `Shortcode` terminology.
- Updated the format settings labels to `ACCENT`, `FONT`, and `BORDER RADIUS`.
- Renamed the shortcode builder filter label to `TIPO PROVA`.

## v2.3.2 - 2026-10-02

### Description
Renamed the exam and degree-session database sections.

### Type
Patch

### Notes
- Renamed the exam section to `Database Appelli d'Esame` in navigation and its page heading.
- Renamed the degree-session section to `Database Sessioni di Laurea` in navigation and its page heading.
- Preserved the existing tab keys and URLs.

## v2.3.1 - 2026-10-02

### Description
Refined standalone month capitalization and date-format examples.

### Type
Patch

### Notes
- Capitalized standalone month names in the public table and admin month filter while keeping month-year headings and dates lowercase.
- Standardized every date-format selector label to show an example followed by its format pattern, using 25/08/1998 as the reference date.
- Kept `DD/MM/YYYY` as the default and labeled the extended option `25 agosto 1998 (DD month YYYY)`.

## v2.3.0 - 2026-10-02

### Description
Added Italian month names and configurable date display formats.

### Type
Minor

### Notes
- Added date-format presets, including the Italian long format `1 settembre 2026`, with `DD/MM/YYYY` as the default.
- Applied the selected format to public calendar dates and admin repository dates.
- Made month names Italian in table, grid, and month-filter output regardless of the WordPress locale.
- Renamed the visible Grafica section to Formato while preserving existing appearance settings.
- Translated remaining plugin-interface labels, plugin description, and bundled example values into Italian.
- Kept native date-edit fields and imported/database date values unchanged.

## v2.2.25 - 2026-09-04

### Description
Removed the final parent-structure difference from the admin title rows.

### Type
Patch

### Notes
- Made Database Esami use the same direct shared heading structure as every other content card.
- Removed the remaining nested title wrapper and kept the shared title spacing rules.

## v2.2.24 - 2026-09-04

### Description
Unified admin title structure and removed parent layout offsets.

### Type
Patch

### Notes
- Replaced the remaining view-specific title wrapper usage with one shared heading class.
- Made the shared heading the direct first child of every content card, including Database Esami.
- Normalized title and description spacing with explicit heading rules.
- Moved the edit back-link outside the card so it cannot shift the title position.

## v2.2.23 - 2026-09-04

### Description
Corrected the remaining admin title alignment differences.

### Type
Patch

### Notes
- Anchored the Importazione heading to the same top edge as the other sections.
- Normalized navigation tab title height, alignment, and line-height.

## v2.2.22 - 2026-09-04

### Description
Standardized admin section title placement and removed confirmed dead code.

### Type
Patch

### Notes
- Routed every admin section heading through the shared section-intro wrapper.
- Removed unused admin and public CSS selectors.
- Removed the unused accent RGB variable and unused notice type metadata.

## v2.2.21 - 2026-09-04

### Description
Aligned Importazione section spacing with the other admin sections.

### Type
Patch

### Notes
- Restored the shared card padding for the Importazione title.
- Applied the shared section-intro wrapper used by the other tabs.
- Made the Browse/Import subform padding uniform on all sides.

## v2.2.20 - 2026-09-04

### Description
Corrected Importazione control rendering and balanced card spacing.

### Type
Patch

### Notes
- Centered the native Browse control text without flex rendering artifacts.
- Kept Browse and Import on one row with fixed shared control geometry.
- Reduced inner and outer vertical padding symmetrically.

## v2.2.19 - 2026-09-04

### Description
Corrected the final Importazione control rendering and vertical spacing.

### Type
Patch

### Notes
- Vertically centered Browse and Import on the same row.
- Replaced the flex-based native Browse button rendering that clipped its label.
- Matched control typography and reduced excess lower card space.

## v2.2.18 - 2026-09-04

### Description
Completed the Importazione control alignment and sizing cleanup.

### Type
Patch

### Notes
- Normalized Browse and Import typography, dimensions, padding, and width.
- Kept both controls on the same row with matching top alignment.
- Reduced inner and outer Importazione spacing while preserving the existing structure.

## v2.2.17 - 2026-09-04

### Description
Aligned Browse and Import on the same row and vertical edge.

### Type
Patch

### Notes
- Kept both controls side by side.
- Forced the Import button to use the same top alignment and control height as the file input.

## v2.2.16 - 2026-09-04

### Description
Aligned the Import button directly beneath the file control.

### Type
Patch

### Notes
- Removed the end alignment that pushed the Import button to the far edge.
- Kept the existing Importazione card structure and Browse alignment unchanged.

## v2.2.15 - 2026-09-04

### Description
Removed the unintended left gap before the Importazione Browse control.

### Type
Patch

### Notes
- Removed left and right padding from the file input container.
- Left the existing Importazione structure and all unrelated styles unchanged.

## v2.2.14 - 2026-09-04

### Description
Restored the original Importazione structure and corrected plugin author metadata.

### Type
Patch

### Notes
- Restored the original two-column import card and nested form panel.
- Kept only the existing file-control and button alignment corrections.
- Set the plugin author to `LatinoF`.

## v2.2.13 - 2026-09-04

### Description
Aligned Importazione with the shared admin card layout and Shortcode notice spacing.

### Type
Patch

### Notes
- Removed the nested Importazione panel background and border.
- Matched Importazione structure to the other admin sections.
- Removed the extra status-notice margin so it matches the Shortcode validation notice exactly.

## v2.2.12 - 2026-09-04

### Description
Placed action status messages inside their originating forms and sections.

### Type
Patch

### Notes
- Reused the Shortcode validation notice structure for graphics, import, database, and edit messages.
- Added section-specific transient storage only to preserve each message across its redirect.
- Removed the global notice slot that caused messages to appear outside their originating section.

## v2.2.11 - 2026-09-04

### Description
Corrected tab hover behavior, import control alignment, and status notice routing.

### Type
Patch

### Notes
- Kept tab hover static with text color only and no movement or animated transition.
- Aligned the file chooser and Import button in one consistent responsive layout.
- Scoped status notices to their originating admin tab so they cannot appear in another section.

## v2.2.10 - 2026-09-04

### Description
Corrected admin palette states, import layout, shadows, tabs, and notice styling.

### Type
Patch

### Notes
- Matched the version pill to the primary button accent.
- Added a static tab hover state without text movement.
- Changed shadows to neutral black and removed accent-colored shadow treatment.
- Rebuilt the import form layout for consistent desktop and mobile alignment.
- Normalized all WordPress notice variants to the derived notice palette.

## v2.2.9 - 2026-09-04

### Description
Removed WordPress table skinning and blue tab focus artifacts from the admin interface.

### Type
Patch

### Notes
- Removed `widefat striped` from the Database Esami table.
- Added complete derived styling for admin table rows, borders, hover state, and width.
- Replaced WordPress blue focus outlines on tabs with accent-colored focus styling.

## v2.2.8 - 2026-09-04

### Description
Removed remaining WordPress default colors from plugin-controlled sections.

### Type
Patch

### Notes
- Styled generic admin buttons, notices, file controls, form fields, and pagination with the derived palette.
- Prevented WordPress striped-table colors from leaking into admin and public calendar rows.
- Preserved all grouping, ordering, and data logic.

## v2.2.7 - 2026-09-04

### Description
Removed legacy hardcoded secondary palette values from the CSS fallbacks.

### Type
Patch

### Notes
- CSS fallback shades now derive from the master accent with `color-mix()`.
- Retained `#037aa4` only as the intentional default accent value.
- Removed hardcoded RGB shadow fallback usage.

## v2.2.6 - 2026-09-04

### Description
Made the complete palette derive from the selected accent without fixed legacy hue targets.

### Type
Patch

### Notes
- Derived deep, soft, row, surface, card, text, muted, line, border, and contrast colors from the accent.
- Removed fixed blue target influence from custom accent colors.
- Preserved existing grouping, ordering, and table rendering logic.

## v2.2.5 - 2026-09-04

### Description
Removed remaining independent white colors from the themed calendar and admin UI.

### Type
Patch

### Notes
- Added derived card and contrast colors to the shared accent palette.
- Applied those derived colors to public headers, cards, and admin headers/cards.
- Preserved all grouping and table data behavior.

## v2.2.4 - 2026-09-04

### Description
Corrected complete custom accent propagation and restored alternate group coloring.

### Type
Patch

### Notes
- Fixed mismatched palette keys that prevented custom derived colors from reaching the public calendar.
- Routed surfaces, borders, separators, text, and admin controls through derived palette variables.
- Restored the existing `calendari-row-alt` group coloring without changing grouping logic.

## v2.2.3 - 2026-09-04

### Description
Fixed custom accent palette propagation.

### Type
Patch

### Notes
- Corrected the shortcode renderer to use the shared derived palette keys.
- Applied derived surfaces and borders to the remaining themed CSS areas.
- Restored visible alternate-group coloring without changing table grouping logic.

## v2.2.2 - 2026-09-04

### Description
Corrected the derived table palette hierarchy.

### Type
Patch

### Notes
- Strengthened the outer table border using the accent-derived border color.
- Kept internal separators soft and maintained the reference blue row hierarchy.

## v2.2.1 - 2026-09-04

### Description
Completed the accent palette and per-field graphics reset implementation.

### Type
Patch

### Notes
- Applied the shared derived palette to the admin settings interface.
- Added working reset controls for border radius, font, and accent.
- Ensured table border colors are derived from the selected accent.

## v2.2.0 - 2026-09-04

### Description
Extended accent-based palette generation and added per-field graphics resets.

### Type
Minor

### Notes
- Derived table and admin setting borders from the selected accent.
- Applied the table palette to the administration settings interface.
- Added individual reset actions for border radius, font, and accent.
- Preserved `#037aa4` as the default accent.

## v2.1.0 - 2026-09-04

### Description
Simplified calendar color customization to a single accent color.

### Type
Minor

### Notes
- Kept the table header exactly equal to the selected accent.
- Derived soft, deep, row, and text colors automatically from the accent.
- Removed independent header, row, text, and secondary accent color controls.
- Set `#037aa4` as the default accent.

## v2.0.5 - 2026-09-04

### Description
Fixed the shortcode validation notice at its source.

### Type
Patch

### Notes
- Replaced the generic WordPress notice component with a dedicated form validation element.
- Removed the JavaScript repositioning workaround.
- Kept the validation element directly after the preview button in the form grid.

## v2.0.4 - 2026-09-04

### Description
Added a client-side fallback for the shortcode validation notice position.

### Type
Patch

### Notes
- Moves the validation warning after the preview button when it arrives through the generic admin notice path.
- Preserves the full-width builder notice styling.

## v2.0.3 - 2026-09-04

### Description
Fixed the shortcode validation notice appearing in the global admin notice area.

### Type
Patch

### Notes
- Prevented generic transient notices from rendering above the tabs on the Shortcode tab.
- Kept the missing-fields validation notice directly below `Genera anteprima`.

## v2.0.2 - 2026-09-04

### Description
Fixed the position of the shortcode builder validation notice.

### Type
Patch

### Notes
- Kept the missing academic-year and course-type notice directly below the preview button.
- Made the notice span the full builder form width.

## v2.0.1 - 2026-09-04

### Description
Simplified the administration panel labels and headings.

### Type
Patch

### Notes
- Removed redundant kicker labels from the admin sections.
- Renamed the preview heading to `Anteprima`.
- Renamed the graphics section to `Personalizzazione`.
- Renamed the repository heading to `Database Esami`.
- Simplified the import form labels and button text.
- Renamed the table corner control to `Border radius`.

## v2.0.0 - 2026-09-04

### Description
Separated exam-type column visibility from exam-type filtering in the shortcode builder and renderer.

### Type
Major

### Notes
- Replaced the combined `tipo_prova` shortcode parameter with `mostra_tipo_prova` and `filtro_tipo_prova`.
- Added independent `Mostra`/`Nascosta` column control and `Tutti`/`Orali`/`Scritte` filtering.
- Changed the generated shortcode display to a fixed-height readonly text input labeled `SHORTCODE`.
- Existing shortcodes using `tipo_prova` require regeneration with the new builder.

## v1.4.0 - 2026-09-03

### Description
Improved the shortcode builder with database-backed selections and content type handling.

### Type
Minor

### Notes
- Added the first `Tipologia` selector with `Esami` and `Laurea (WIP)` options.
- Replaced manual `AA_ID` and `TIPO_CORSO` fields with database-backed dropdowns.
- Limited course-type choices to values available for the selected academic year.
- Added a clear work-in-progress state for degree-session shortcodes.

## v1.3.3 - 2026-09-03

### Description
Corrected vertical text alignment in administration dropdowns.

### Type
Patch

### Notes
- Removed vertical padding from admin selects.
- Applied a compact line height so dropdown labels are vertically centered.

## v1.3.2 - 2026-09-03

### Description
Normalized the administration control dimensions.

### Type
Patch

### Notes
- Unified text inputs, dropdowns, numeric fields, file fields, and buttons at the text-field height.
- Aligned button content vertically within the shared control height.

## v1.3.1 - 2026-09-03

### Description
Corrected shortcode localization and value handling.

### Type
Patch

### Notes
- Translated all public calendar labels into Italian.
- Removed the legacy English shortcode aliases.
- Normalized generated course values and view values to lowercase.

## v1.3.0 - 2026-09-03

### Description
Restructured and redesigned the WordPress administration panel.

### Type
Minor

### Notes
- Added Shortcode, Database Esami, Database Sessioni di Laurea, Grafica, and Importazione tabs.
- Added visual shortcode generation, copy action, and server-side preview.
- Added saved graphics settings for table radius, palette, and font family.
- Translated the administration interface into Italian.
- Replaced the legacy administration styling with the calendar visual language.

## v1.2.11 - 2026-09-03

### Description
Applied the equal title spacing consistently on mobile.

### Type
Patch

### Notes
- Removed the mobile override that expanded the title gap to `2rem`.
- Kept the shared `1.15rem` spacing between title, pills, and table at all widths.

## v1.2.10 - 2026-09-03

### Description
Equalized spacing between the calendar title, CDS pills, and table.

### Type
Patch

### Notes
- Reduced the excessive title-to-table spacing.
- Reused the same gap between title, pills, and table.
- Avoided rendering an empty pill container when pills are not applicable.

## v1.2.9 - 2026-09-03

### Description
Refined calendar heading and table header presentation.

### Type
Patch

### Notes
- Reduced the calendar title size and explicitly applied Nunito.
- Removed the `Exam sessions` label while preserving CDS pills.
- Removed table-header letter spacing.

## v1.2.8 - 2026-09-03

### Description
Matched the table header and Exam date typography.

### Type
Patch

### Notes
- Set both table headers and Exam date to the requested `700` weight and matching size.
- Kept Month at `700` and the remaining table content at regular weight.
- Removed the unrequested `800` adjustment.

## v1.2.7 - 2026-09-03

### Description
Increased the visual weight of the required bold table elements.

### Type
Patch

### Notes
- Set headers, Month, and Exam date to weight `800`.
- Kept Exam type, Booking window, and Venue at regular weight.

## v1.2.6 - 2026-09-03

### Description
Explicitly preserved bold weight for table headers.

### Type
Patch

### Notes
- Protected table header weight from theme overrides.
- Kept the intended bold set limited to headers, Month, and Exam date.

## v1.2.5 - 2026-09-03

### Description
Made table font weights deterministic against theme overrides.

### Type
Patch

### Notes
- Explicitly enforced bold weight for Exam date.
- Explicitly kept Exam type, Booking window, and Venue regular weight.
- Preserved bold weight for Month and table headers.

## v1.2.4 - 2026-09-03

### Description
Adjusted table typography to limit bold text to key fields.

### Type
Patch

### Notes
- Kept table headers bold.
- Made Month and Exam date bold.
- Set Exam type, Booking window, and Venue to regular weight.

## v1.2.3 - 2026-09-03

### Description
Corrected table corner rendering after proportional scaling.

### Type
Patch

### Notes
- Moved the table border and radius to the overflow wrapper.
- Removed conflicting radius and border rendering from the transformed table.
- Accounted for the wrapper border in the scaled height calculation.

## v1.2.2 - 2026-09-03

### Description
Improved table fit and removed the duplicated table background surface.

### Type
Patch

### Notes
- Removed the nested appeal background behind the table.
- Kept table content on single lines without horizontal scrolling.
- Added proportional table scaling when the available width is smaller than the table.
- Recalculated the wrapper height after scaling to prevent clipped content.

## v1.2.1 - 2026-09-03

### Description
Corrected calendar table grouping and exam-type column placement.

### Type
Patch

### Notes
- Grouped table bodies by month and `TIPO_PROVA`, not by booking window.
- Placed `TIPO_PROVA` as the second table column with group-level rowspan.
- Kept booking-window rowspans inside each type group without using them as group boundaries.

## v1.2.0 - 2026-09-03

### Description
Added optional exam-type data and shortcode display/filter modes.

### Type
Minor

### Notes
- Added optional `TIPO_PROVA` import, storage, validation, and admin editing.
- Accepted non-empty exam types are `Orale` and `Scritta`.
- Added case-insensitive shortcode modes for showing all types or filtering by type.
- Restored the active CSS to the pre-v1.1.8 state.

## v1.1.8 - 2026-09-03

### Description
Prevented theme CSS from reintroducing unwanted calendar cell borders.

### Type
Patch

### Notes
- Added a scoped high-priority reset for table body cell borders.
- Preserved only the plugin-controlled full-width group separators.
- Protected table border-collapse and header border behavior from theme overrides.

## v1.1.7 - 2026-09-03

### Description
Rebuilt grouped table separators to eliminate cell-border artifacts.

### Type
Patch

### Notes
- Rendered each booking-window group in its own table row group.
- Removed borders from spanning cells.
- Added one continuous divider between booking groups.

## v1.1.6 - 2026-09-03

### Description
Matched the reference table cell structure and border behavior.

### Type
Patch

### Notes
- Replaced body month header cells with reference-compatible data cells.
- Removed intermediate borders inside grouped rows.
- Applied borders only to spanning cells and booking-group boundaries.
- Removed the final-row border beneath the table content.

## v1.1.5 - 2026-09-03

### Description
Corrected grouped table borders to match the reference layout.

### Type
Patch

### Notes
- Removed intermediate borders inside multi-row booking groups.
- Kept borders on group boundaries and spanning cells only.
- Preserved clean borders for multi-group months such as November.

## v1.1.4 - 2026-09-03

### Description
Aligned month row spans and group borders with the reference calendar layout.

### Type
Patch

### Notes
- Rendered one month cell per booking-window group.
- Corrected multi-group months such as November.
- Restored clean group-end borders across all table cells.

## v1.1.3 - 2026-09-03

### Description
Fixed grouped rendering for multiple exam dates within the same month.

### Type
Patch

### Notes
- Added month row spans for consecutive records in the same month.
- Added booking-window row spans for records sharing the same booking dates.
- Kept each exam date and venue on its own row.

## v1.1.2 - 2026-09-03

### Description
Fixed imports containing multiple exam dates in the same month.

### Type
Patch

### Notes
- Included `DATA_ESAMI` in the record identity.
- Added a safe database migration from the previous four-field identity.
- Preserved update behavior for the same course and exam date.

## v1.1.1 - 2026-09-03

### Description
Fixed multi-venue rendering for imported calendar records.

### Type
Patch

### Notes
- Split semicolon-, comma-, and line-break-separated venues into separate lines.
- Applied the venue formatting consistently in table, grid, and admin output.
- Preserved the original stored venue value.

## v1.1.0 - 2026-09-03

### Description
Added venue support to imported and rendered calendar records.

### Type
Minor

### Notes
- Added the `SEDE` field to the repository and database migration.
- Added venue import support for CSV and XLSX files.
- Added venue editing and display in the admin repository and shortcode.
- Preserved compatibility with legacy seven-column imports.

## v1.0.1 - 2026-09-03

### Description
Refined the public calendar presentation to match the approved manual design.

### Type
Patch

### Notes
- Made the table the default shortcode view.
- Added the Nunito, blue, teal, and light-blue visual system.
- Added scoped CSS to reduce theme-style interference.
- Limited CDS ID pills to Master, CO, and CP calendars.
- Removed CDS ID from public output for other calendar types.

## v1.0.0 - 2026-09-03

### Description
Initial release of the Calendari WordPress plugin.

### Type
Minor

### Notes
- Added a custom calendar repository with a composite record identity.
- Added CSV and XLSX imports with validation and insert-or-update behavior.
- Added an admin repository for searching, editing, and deleting records.
- Added the `[calendari]` shortcode with table, grid, and combined views.
