# Tutorial Content Authoring

## Purpose

Tutorial content is data-backed and screen-scoped. The runtime loader discovers one definition per screen from:

```text
app/Modules/Platform/Tutorials/*.php
```

`TutorialRegistry` owns discovery, identity validation, route lookup, and Screen ID lookup. Screen definitions own only localized content and stable selectors.

## Add a new screen

1. Confirm that the named route already exists. Do not register a fabricated route.
2. Create a file named after the canonical Screen ID, for example:

   ```text
   app/Modules/Platform/Tutorials/UI-CAT-009.php
   ```

3. Return one guide array with the required keys:

   ```php
   <?php

   return [
       'screen_id' => 'UI-CAT-009',
       'route_names' => ['catalog.example'],
       'title' => ['ar' => '...', 'en' => '...'],
       'purpose' => ['ar' => '...', 'en' => '...'],
       'when_to_use' => ['ar' => '...', 'en' => '...'],
       'permissions' => ['capability.view'],
       'approved_actions' => [
           [
               'key' => 'capability.view',
               'label' => ['ar' => 'عرض السجل', 'en' => 'View the record'],
               'required_permission' => 'capability.view',
           ],
       ],
       'stories' => ['US-046'],
       'flows' => ['FLW-CAT-01'],
       'acceptance_criteria' => ['AC-UI-08', 'AC-UI-09', 'AC-UI-11', 'AC-UI-12'],
       'sections' => [
           'steps' => [
               [
                   'key' => 'table',
                   'selector' => '[data-guide="example-table"]',
                   'title' => ['ar' => 'جدول السجل', 'en' => 'Record table'],
                   'body' => ['ar' => '...', 'en' => '...'],
               ],
           ],
           'fields' => [],
           'notes' => ['ar' => '...', 'en' => '...'],
           'warnings' => ['ar' => '...', 'en' => '...'],
           'errors' => ['ar' => '...', 'en' => '...'],
           'next_step' => ['ar' => '...', 'en' => '...'],
           'faq' => ['ar' => '...', 'en' => '...'],
       ],
       'tour_steps' => [
           [
               'key' => 'table',
               'selector' => '[data-guide="example-table"]',
               'title' => ['ar' => 'جدول السجل', 'en' => 'Record table'],
               'body' => ['ar' => '...', 'en' => '...'],
           ],
       ],
       'version' => '1.0',
       'updated_at' => 'YYYY-MM-DD',
   ];
   ```

4. Add stable `data-guide` attributes to the actual rendered elements.
5. Add the Screen ID and route to `docs/40-contextual-page-guide-specification.md` and `.ai/UI_SCREENS.md`.
6. Verify both Arabic RTL and English LTR, including missing-target fallback and permission filtering.

## Add a step to an existing screen

Edit only that screen's definition file. Keep `sections.steps` and `tour_steps` aligned. A tour selector must point to a real, stable element and should not expose private values.

For shared bulk operations, use the stable selector:

```css
[role="region"][aria-label="Bulk operations"]
```

The current catalog and admin table guides document current-page selection, selection limits, confirmation, and the permitted status action without implying cross-page processing.

## Safety rules

- Use bilingual `ar` and `en` content for every visible title/body.
- Reference permissions, never raw roles or user IDs.
- Do not put model payloads, customer data, costs, secrets, tokens, private paths, or download URLs in guide data.
- Keep route names real and named.
- Keep business transitions in domain actions; tutorials explain behavior but never authorize it.
- Use selectors that survive Livewire re-rendering and responsive layout changes.
- Update the version and date when content changes.
