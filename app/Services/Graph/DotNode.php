<?php

namespace App\Services\Graph;

/**
 * Builds a DOT node declaration using an HTML-like label (a borderless 2-row table: the icon image
 * on top, the name/detail lines below) instead of the plain `image=... width=... height=...` node
 * attributes every graph builder used before. That attribute-based approach fought two separate
 * Graphviz behaviors that HTML-like labels don't have:
 * - `image=` draws at the file's own native pixel size unless `imagescale` is set, and the icon
 *   PNGs shipped under public/images/ have wildly different native dimensions — nodes rendered at
 *   visibly inconsistent sizes despite every builder declaring the same node width/height.
 * - a node's declared width/height is only a MINIMUM: Graphviz silently grows it (and the icon
 *   scaled to fit it) past that whenever the label below the icon is wider, which is the common
 *   case for any reasonably long name — making a width/height reduction look like it has no effect.
 *   Forcing the box to the declared size instead (`fixedsize=true`) stops that, but then clips the
 *   label text outright whenever it doesn't fit, which is worse.
 *
 * An HTML-like label sidesteps both: Graphviz scales the `<IMG>` to fit its own table cell
 * regardless of the image file's native size, and the label text row is free to size itself (and
 * the table) as wide as it needs, independently of the fixed-size image row above it — nothing
 * gets clipped.
 */
class DotNode
{
    /**
     * Icon width in points: 20% smaller than the 1in x 1.1in box every builder used before
     * (1in x 0.8 = 0.8in = 57.6pt).
     */
    private const ICON_WIDTH_PT = 57.6;

    /**
     * Icon height in points (1.1in x 0.8 = 0.88in = 63.36pt).
     */
    private const ICON_HEIGHT_PT = 63.36;

    /**
     * @param  array<int, string>  $labelLines  One or more text lines shown below the icon (already
     *                                          HTML-escaped by the caller, e.g. via e()), each its
     *                                          own table row — a name, and optionally an IP/detail
     *                                          line underneath it.
     * @param  string  $hrefAttr  The full ` href="..."` attribute fragment (leading space included),
     *                            or '' for no link — callers already compute this via their own
     *                            href() helper.
     */
    public static function withImage(string $nodeId, string $imagePath, array $labelLines, string $hrefAttr = ''): string
    {
        $rows = '';
        foreach ($labelLines as $line) {
            $rows .= '<TR><TD>'.$line.'</TD></TR>';
        }

        return $nodeId.' [shape=none label=<'.
            '<TABLE border="0" cellborder="0" cellspacing="0">'.
            '<TR><TD width="'.self::ICON_WIDTH_PT.'" height="'.self::ICON_HEIGHT_PT.'" fixedsize="true"><IMG SRC="'.$imagePath.'"/></TD></TR>'.
            $rows.
            '</TABLE>>'.$hrefAttr.']';
    }
}
