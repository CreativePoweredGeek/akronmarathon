# TS Fielder's Choice (EE7)

Category picker fieldtype for **Fluid** (and channel/grid). Editors choose a category **by name** in a dropdown; the field stores `cat_id` (no ID lookup).

Inside **Fluid** and **Grid**, the publish UI uses a native `<select>` so category picking works when blocks are added or cloned (EE’s React dropdown is only wired for the core Select fieldtype).

## Deploy

Copy `ee7-addons/ts_fielders_choice/` → `system/user/addons/ts_fielders_choice/`, install via **Add-ons**.

## Field settings

1. **Category group** — list all categories in one group (typical for FAQ topics).
2. **Channel** — union of categories from category groups assigned to that channel (e.g. FAQs channel).

## Fluid FAQ block

1. Add a Fluid field block using **TS Fielder's Choice** (e.g. short name `faq_category`).
2. On publish, pick the category from the dropdown.

## Templates

Default tag outputs the category ID (for `{exp:channel:entries category="..."}`):

```html
{exp:channel:entries channel="faqs" category="{faq_category}" dynamic="no"}
  ...
{/exp:channel:entries}
```

Optional `format` parameter:

| Tag | Output |
|-----|--------|
| `{faq_category}` | `cat_id` |
| `{faq_category format="name"}` | Category title |
| `{faq_category format="url_title"}` | URL title |
| `{faq_category format="group_id"}` | Category group id |
