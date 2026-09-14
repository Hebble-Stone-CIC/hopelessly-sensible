# wordpress.org assets

These do **not** go in the plugin zip. They live in the `assets/` directory of
the plugin's Subversion repository, alongside `trunk/` and `tags/`, and
wordpress.org serves them on the plugin page.

Not to be confused with the plugin's own `assets/` folder, which ships and
contains `admin.css`.

| File | Shown as | Caption in readme.txt |
| --- | --- | --- |
| `banner-772x250.png` | Header across the top of the plugin page | none |
| `banner-1544x500.png` | The same, for high-density screens | none |
| `icon-128x128.png` | The square in search results and the plugin installer | none |
| `icon-256x256.png` | The same, for high-density screens | none |
| `screenshot-1.png` | Screenshot 1 | The settings screen. Every option carries a plain explanation and a note on what it might break. |
| `screenshot-2.png` | Screenshot 2 | An option this site cannot use, still on the screen, saying what is stopping it. |

The banner and icon are Lewis's artwork, resized from the originals in
`~/Downloads` (`banner-hopelessly-sensible-security-hardening.png`, 1364 by
442, and `logo-hopelessly-sensible-plugin.png`, 539 by 541). The 1544 by 500
banner is a small upscale of that original; swap in a native export if one
turns up. The screenshots are 2x captures of the test-new-woo Local site at an
1100 pixel viewport, cropped to one group each: the first with WooCommerce
active, the second with it deactivated so the reviews row shows blocked.

The numbering is what ties them to the captions: `screenshot-1.png` is the first
entry under `== Screenshots ==` and so on. Renaming one silently re-captions it.
