# Maps in Mercator

## Introduction

Mercator's map editor lets you **freely place cartography objects on a canvas** (schema) and position, connect, and
style them as you see fit: servers, applications, networks, sites, flows, cables…

Unlike the auto-generated views (Graphviz for static views, Vis.js for exploration), a map is a **persistent and
editable** document: its layout is saved and stays stable from one session to the next, which makes it the ideal
format for presentation or reference schemas (security committee, architecture documentation, reports…).

Technically, the editor is built on the **MaxGraph** library. It is accessible from
**Administration → Cartography → Maps**, either when creating or editing an existing map.

## Creating or opening a map

A map is identified by:

- a **name** (required),
- a **type**, used to classify maps among themselves (e.g. network, application, site…).

When opened, the map displays the objects already positioned from previous sessions, along with the links connecting
them.

## Adding objects to the map

### Filtering available objects

The **Objects** selector lists all cartography objects that can be added to the schema. It can be narrowed down with
two combinable filters:

- **Filter** (by view): Ecosystem, Information system, Applications, Administration, Logical infrastructure, Flows,
  Physical infrastructure, Network infrastructure, Physical links;
- **Attributes**: filters objects by the tags/attributes associated with them.

### Adding a specific object

Once an object has been chosen in the **Objects** selector, its icon appears next to the **Add** button. There are
two ways to place it on the canvas:

- click **Add**: the object is inserted at the center of the visible area;
- **drag and drop** the icon directly to the desired spot on the canvas.

If the object is already on the map, this action does not duplicate it: it selects the existing object instead (and
also restores any links that may have been mistakenly removed).

### Automatically deploying linked objects

Two mechanisms make it possible to automatically bring in the objects linked to one already present on the map:

- **Double-click** on an icon: the directly linked objects not yet on the schema are added and arranged in a circle
  around the object, along with their links;
- **Deploy**: performs the same operation recursively, over several levels, starting from the selected object. An
  object must be selected before clicking the button, otherwise a warning message is shown.

Both actions respect the active view/attribute filters as well as the following options, located next to the
**Deploy** button:

- **Depth** (1 to 5): number of link levels explored by the recursive deployment;
- **Direction**: restricts the deployment to **Upstream**, **Downstream**, or **Both** objects (based on the order of
  the cartography views).

!!! tip "Avoiding unwanted repositioning"
    Double-click and automatic deployment only place **new** objects; objects already positioned manually do not
    move. However, if the physics engine (see below) is active, the entire schema may reorganize itself after the
    addition — turn it off if you want to keep a fixed layout.

### Restarting or updating the map

- **Restart** completely clears the canvas (except for the background), letting you start over from an empty map
  without deleting the map itself;
- ![Update](images/icons/lightning-fill.svg){: .toolbar-icon } **Update** resynchronizes the objects already present with the current cartography data:
  labels and icons are refreshed, and objects that were deleted from the cartography are removed from the schema.
  Cable-type links keep their custom color and style during this update.

## Organizing the map

### Moving and aligning objects

- move with the mouse (drag and drop);
- fine-tune movement with the keyboard arrow keys (1 pixel per press), once the object is selected;
- ![Grid](images/icons/grid-3x3-gap.svg){: .toolbar-icon } **Grid**: displays a reference grid over the canvas to help with visual alignment.

### Physics engine (magnet)

![Physics](images/icons/magnet.svg){: .toolbar-icon } The **magnet** icon activates a repulsion engine between objects: icons push each other apart to avoid
overlapping and automatically arrange themselves around groups. This feature is **disabled by default** and must be
turned on deliberately — it never kicks in on its own during a simple manual edit of the schema.

!!! info "Objects inside a group"
    When the physics engine is active, objects placed inside a frame (group) stay confined within its boundaries:
    they cannot escape it or migrate into another group.

### Grouping / ungrouping objects

After selecting several elements (objects, texts, frames…), the ![Group](images/icons/plus-square-dotted.svg){: .toolbar-icon } **Group** button gathers them into a single
group, which can then be moved as a whole. ![Ungroup](images/icons/dash-square-dotted.svg){: .toolbar-icon } **Ungroup** releases the elements from the selected group.

### Annotating the schema: text and frames

Two annotation elements can be dragged from the side toolbar onto the canvas:

- ![Text](images/icons/fonts.svg){: .toolbar-icon } **Text**: a free text box, editable by clicking on it;
- ![Border](images/icons/bounding-box.svg){: .toolbar-icon } **Border**: a grouping/annotation rectangle, useful for visually delimiting an area
  (a site, a DMZ, an environment…).

Right-clicking a text or frame opens a formatting context menu:

- for a **text**: font, size, color, bold, italic, underline;
- for a **link** (see below): color, line style and thickness, routing.

### Styling links

Right-clicking a link (or a selection of several links) opens a context menu that lets you set:

- the **color** of the line;
- the line **style**: solid, dashed, dotted, dash-dot;
- the **thickness** (1 to 5 px);
- the **routing**: straight line, arc, or right angles (orthogonal).

These settings can be applied to several selected links at once. When several links exist between the same two
objects, they are automatically spread apart as arcs to stay readable.

### Background (wallpaper)

![Background](images/icons/image.svg){: .toolbar-icon } The **Background** icon opens a menu that lets you:

- pick an image from a predefined gallery;
- **import** your own image from your computer;
- **remove** the current background.

The image occupies the canvas background at its original size and always stays **behind** the other elements: it can
be neither selected nor moved, to avoid accidentally displacing it while editing the schema.

### Zoom and full screen

- ![Zoom in](images/icons/zoom-in.svg){: .toolbar-icon } **Zoom in** / ![Zoom out](images/icons/zoom-out.svg){: .toolbar-icon } **Zoom out**: zoom in/out on the canvas;
- ![Recenter](images/icons/crosshair.svg){: .toolbar-icon } **Recenter**: automatically frames all the objects of the schema on screen;
- the icon at the top right of the editing area **expands/collapses** the editor to full screen, hiding the sidebar
  to maximize the working space.

### Showing IP and attributes

Two toggles let you enrich the labels displayed under each icon:

- **Show IP**: displays the object's IP address, when it exists;
- **Show Attr**: displays the object's attributes/tags.

## Undo / redo and saving

- ![Undo](images/icons/arrow-counterclockwise.svg){: .toolbar-icon } **Undo** / ![Redo](images/icons/arrow-clockwise.svg){: .toolbar-icon } **Redo** (or `Ctrl+Z` / `Ctrl+Y`) undo or restore the latest changes to the schema;
- **Delete** (`Del`/`Backspace` key or the **Delete** button) removes the selected elements from the canvas
  (the background cannot be removed this way);
- ![Save](images/icons/floppy-fill.svg){: .toolbar-icon } **Save** immediately saves the map in the background, without reloading the page;
- the **Save** button, at the bottom of the page, saves the map and then returns to the map list.

!!! warning "Unsaved changes"
    If the map contains unsaved changes, Mercator asks for confirmation before leaving the page (closing the tab,
    navigating away, or clicking **Back to list**).

## Exporting the map

The ![Export](images/icons/download.svg){: .toolbar-icon } **Export** button generates an **SVG** file of the schema as displayed on screen, images included
(they are embedded directly in the file). This vector format is especially well suited for reuse in a report or a
presentation.

## Keyboard shortcuts

| Shortcut | Action |
|---|---|
| `Ctrl+Z` | Undo |
| `Ctrl+Y` | Redo |
| `Del` / `Backspace` | Delete the selection |
| `↑` `↓` `←` `→` | Move the selection by one pixel |
| Double-click on an icon | Deploy directly linked objects |
| Drag and drop | Add an object, a text, or a frame |
| Right-click on a link | Open the link formatting menu |
| Right-click on a text | Open the text formatting menu |

## Best practices

- Turn off the physics engine once you've achieved the desired layout, to avoid having the whole schema reorganize
  itself when a new object is added.
- Favor reasonably sized background images: an oversized image may exceed the visible area of the canvas and end up
  partially hidden.
- Use the **Update** button after a change in the cartography (renaming, icon change…) to resynchronize an existing
  map without having to rebuild it.
- Remember to click the **floppy disk** icon regularly during a long editing session, in addition to the final save
  via the **Save** button.
