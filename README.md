# Flatsome Breadcrumbs

UX Builder breadcrumbs for [Flatsome](https://uxthemes.com/flatsome/). The trail is built from the main query and collapses overflow into an ellipsis menu, in the style of shadcn breadcrumbs.

**Version:** 1.0.0  
**Author:** Benjamin Pedersen  
**License:** GPLv2 or later  
**Text domain:** `flatsome-breadcrumbs`  
**Shortcode:** `[ux_breadcrumbs]`

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Flatsome (UX Builder) for the Layout element

The shortcode still renders if Flatsome is inactive. The UX Builder element is registered only when Flatsome is available, and an admin notice is shown when it is missing.

## Install

1. Copy this folder to `wp-content/plugins/flatsome-breadcrumbs` (the folder name should be `flatsome-breadcrumbs`).
2. Activate **Flatsome Breadcrumbs** under **Plugins**.
3. In UX Builder, add **Breadcrumbs** from the **Layout** category.

Or place the shortcode in content:

```
[ux_breadcrumbs separator="chevron" align="left" show_home="true" home_label="Home"]
```

## What the trail includes

- Home, then page or post ancestors, then the current item
- Blog index, category, tag, author, date, search, and 404
- Custom post types: archive link when the type has one, then ancestors or a primary term
- With WooCommerce active: shop, product category chain, product, and other product taxonomies (tags, brands, attributes). Cart, checkout, and My Account stay page trails, with the current account or checkout endpoint added when one is open

Yoast and Rank Math primary terms are preferred when they are set. Otherwise the deepest assigned term is used.

## UX Builder options

Options are grouped.

### Content

| Option | Attribute | Default | Notes |
| --- | --- | --- | --- |
| Show home | `show_home` | `true` | |
| Home label | `home_label` | Home | |
| Home as icon | `home_icon` | `false` | House icon, label kept for screen readers |
| Hide current | `hide_current` | `false` | Drops the current page from the visible trail |
| Max items before ellipsis | `max_items` | `0` | How many **middle** crumbs stay visible. `0` collapses only when the row overflows |
| Enable schema markup | `schema` | `false` | JSON-LD `BreadcrumbList` |

### Separator

| Option | Attribute | Default |
| --- | --- | --- |
| Preset | `separator` | `chevron` |

Presets: `chevron`, `slash`, `arrow`, `dot`. They are inline Lucide SVGs and do not need WP Icons.

When **WP Icons** and **WP Icons for Flatsome** are active, a **Custom icon** field (`separator_icon`, option type `wpicons`) is added. A selected icon replaces the preset. The stored value is `library:name` or `library:name:variant` (for example `lucide:chevron-right`).

### Style

| Option | Attribute | Default |
| --- | --- | --- |
| Align | `align` | `left` (`left`, `center`, `right`) |
| Font size | `font_size` | `14` (px) |
| Gap | `gap` | `8` (px) |
| Icon size | `icon_size` | `14` (px) |

Icon size applies to the separator and the home icon.

### Colors

Link, hover, current, and separator are Flatsome `colorpicker` fields. Each helper list includes **Dark**, **White**, **Primary**, **Secondary**, **Success**, and **Alert** (theme colors via `flatsome_option` when that function exists). Helper titles are the English words Flatsome ekstra farger looks for, so extra swatches from that plugin are merged through its existing `ux_builder_shortcode_data` filter.

Rendered colors accept hex (`#rgb`, `#rrggbb`, `#rrggbbaa`), `rgb()` / `rgba()`, `hsl()` / `hsla()`, `var(--token)`, and `rgb(var(--token))`. Anything else is dropped.

### Advanced

| Option | Attribute |
| --- | --- |
| Class | `class` |
| ID | `id` |

## Ellipsis menu

If the trail is wider than the breadcrumb, or `max_items` keeps fewer middle crumbs than the trail has, the earlier middle crumbs collapse:

`Home › … › Components › Breadcrumb`

The ellipsis is a `<button type="button">`. The dropdown lists the hidden pages. Home (when shown) and the current page stay visible. Width is measured with `ResizeObserver`, not a fixed breakpoint. The current label truncates only when Home, the ellipsis, and the current page still do not fit.

Keyboard: Arrow Down on the button opens the menu, Arrow Up/Down plus Home/End move inside it, Escape closes and returns focus to the button, Tab closes it, and a click outside closes it.

Without JavaScript the ellipsis stays hidden and the trail wraps onto extra lines.

In the UX Builder iframe the element shows a sample trail — Home, Docs, Components, Breadcrumb — using the separator and styles you picked.

## Markup

```html
<nav aria-label="Breadcrumb">
  <ol class="fbc-list">
    …
    <li><span aria-current="page">Current</span></li>
  </ol>
</nav>
```

Separators are `aria-hidden`. Schema, when enabled, is a `BreadcrumbList` JSON-LD block. Hide current removes the last crumb from the HTML only; the schema list still includes it.

## Filters

```php
add_filter('fbc_breadcrumb_items', function ($items, $args) {
    // $args: show_home (bool), home_label (string), home_icon (bool).
    $items[] = array(
        'label' => 'Extra',
        'url' => home_url('/extra/'),
    );
    return $items;
}, 10, 2);

add_filter('fbc_breadcrumb_item', function ($item, $index, $items) {
    if ($index === 0) {
        $item['label'] = 'Start';
    }
    return $item;
}, 10, 3);
```

Each item is an array:

| Key | Meaning |
| --- | --- |
| `label` | Plain text. Tags are stripped. |
| `url` | Absolute or relative URL. Empty means not a link. |
| `current` | The last item is treated as the current page after filtering. |
| `icon` | `home` shows the house icon. Anything else is ignored. |

`FBC_Trail::get($args)` returns the same list if you need it from PHP. The builder preview does not run the filters; it always shows the sample trail.

## Soft dependencies

| Plugin | Role |
| --- | --- |
| Flatsome | Required for the UX Builder element |
| WP Icons and WP Icons for Flatsome | Optional `wpicons` separator picker. Presets work without them |
| WooCommerce | Optional shop, category, and product crumbs |
| Flatsome ekstra farger | Optional. Extra color swatches appear because the color pickers expose helper titles |

CSS and JavaScript load when the shortcode is rendered. The builder preview iframe also loads them so the sample trail can collapse.
