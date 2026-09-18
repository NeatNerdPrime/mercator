# Perimeters in Mercator

Perimeters are the main mechanism to partition the cartography. Perimeters let you map **several establishments (or entities, sites, business units) in a single Mercator instance**, while allowing each one to manage its own objects and still be able to create links between objects from different perimeters. This documentation explains what a perimeter is, how it combines with roles, and how users work within their perimeter.

## Introduction — What is a perimeter?

A **perimeter** is a partition of the cartography in which every named object (server, application, network, site…) belongs to **exactly one perimeter**. By default, all objects belong to the **default perimeter** (id = 1).

Perimeters allow you to:

- Host the cartography of **multiple establishments** in a single instance
- Let each establishment **manage its own objects** independently
- Still **identify flows and links** that cross establishments
- Partition visibility and access control by establishment

**Note:** Perimeter management is an **opt-in feature**. Until you enable it, all objects remain in the default perimeter and no filtering is applied.

!!! info "Three mechanisms for access control"
    Mercator combines three complementary notions:
    
    - **Role** defines *what* a user can do (see the *Roles* documentation)
    - **Perimeter** defines *which* establishment's objects those rights apply to
    - **Cartographer** assignment delegates responsibility for **specific individual objects**, regardless of perimeter (see the *Cartographers* documentation)
    
    Roles and perimeters work together: a user's permissions in a perimeter are determined by their role in that perimeter.

!!! note "Technical model"
    - Each object carries a `perimeter_id` integer (never NULL, always ≥ 1)
    - A default perimeter always exists with `id = 1` (created in the database bootstrap migration)
    - Object names are **unique per (perimeter_id, name)** — two establishments can each have a "DNS Server"
    - Perimeters are managed in a dedicated `perimeters` table

## How perimeters work: Before and after activation

### Without perimeter management (default state)

When perimeter management is **disabled**:

- Every object has a `perimeter_id = 1` (the default perimeter)
- **No filtering is applied** — all users see all objects
- There is no perimeter selector in the UI
- The feature is completely transparent

This is the state for all existing Mercator instances by default.

### With perimeter management (after enabling)

When you **enable** perimeter management from **Administration → Configuration → Perimeters**:

- Filtering is **activated**
- Users now see only objects in perimeters their roles grant them access to
- A **perimeter selector** appears (if the user has multiple perimeters)
- Admins can **create new perimeters** (id > 1)
- Admins can **move objects** between perimeters

**Important:** Enabling the feature does not change existing data. All objects remain assigned to the default perimeter (id = 1). Partitioning only takes effect when you create new perimeters and reassign objects.

### The default perimeter

- **Always exists** with `id = 1` (created in the database bootstrap migration)
- Cannot be deleted
- Has a name that you set (initially "Default")
- Cannot be renamed to an empty string
- Is the perimeter for all objects until you explicitly move them

---

## Enabling perimeter management

Perimeter management is **disabled by default**. To enable it:

1. Go to **Administration → Configuration → Perimeters**
2. Toggle **Enable Perimeter Management** to ON
3. Optionally rename the default perimeter (e.g., "Head Office")
4. Save

### What happens when you enable it?

✓ Filtering is activated (users now see only their assigned perimeters)  
✓ Perimeter selector appears for multi-perimeter users  
✓ Admin tools to create/rename/delete perimeters become available  
✓ Object assignment UI gets a perimeter dropdown  
✓ All existing objects remain in the default perimeter (id = 1)  

**Nothing is hidden yet** — until you create new perimeters and reassign objects, all users still see everything.

### What happens when you disable it again?

✓ Filtering is deactivated  
✓ All users see all objects regardless of perimeter assignment  
✓ The perimeter selector disappears from the UI  
✓ Admin perimeter management tools are hidden  
✓ All objects keep their `perimeter_id` (data is not changed)  

You can safely toggle the feature on and off without data loss.

## Managing perimeters

From **Administration → Configuration → Perimeters**, an administrator can:

### Add a perimeter

1. Click **+ Add Perimeter**
2. Enter a **name** (2–32 characters)
3. Click **Create**

The perimeter is assigned a numeric `id` (starting from 2). Object names are unique per perimeter, so two establishments can each have a "DNS Server".

### Rename a perimeter

1. Find the perimeter in the list
2. Click **Edit**
3. Change the name
4. Save

Even the default perimeter can be renamed (e.g., "Head Office").

### Delete a perimeter

A perimeter can be deleted only if:

- It has **no objects** (reassign them first)
- It has **no roles** (reassign users to other roles first)
- It is **not the default perimeter** (id = 1 cannot be deleted)

To delete:

1. Reassign all objects to other perimeters or the default
2. Reassign all roles to other perimeters or the default
3. Click **Delete** in the perimeters list
4. Confirm

Deletion is **permanent** and cannot be undone.

!!! warning "Before deleting a perimeter"
    - Audit: Check if any objects or roles are still assigned
    - Notify users in that perimeter
    - Migrate data to another perimeter if needed

## Roles and perimeters

Each **role** is assigned to **exactly one perimeter**. The role's permissions apply only to objects within that perimeter. (See the *Roles* documentation for complete role management details.)

### Assigning a perimeter to a role

1. **Administration → Roles** → Create or Edit
2. Select a **Perimeter** (dropdown)
3. Select permissions as usual
4. Save

### Multi-perimeter access

A **user** may hold multiple roles, each in a different perimeter. The perimeters the user can access are the **union of all their roles' perimeters**.

#### Example: Multi-establishment team

Alice is responsible for two establishments:

- **Role "Admin"** in Perimeter **1** ("Head Office")
  → Full access to all Head Office objects

- **Role "Viewer"** in Perimeter **2** ("Branch A")
  → Read-only access to all Branch A objects

**Result:** Alice sees objects from both perimeters. She can edit Head Office objects but only view Branch A objects. If a flow connects the two, she sees it from both sides.

### Admin access

**Administrators** are never filtered by perimeter. They always see the entire cartography, regardless of which perimeters exist or how many they manage.

### What happens when perimeter management is disabled?

When you **disable** perimeter management:

- All role perimeter assignments remain unchanged in the database
- But **no filtering is applied** — all users see all objects
- You can re-enable the feature later, and filtering will resume

This allows you to safely toggle the feature without losing role configuration.

!!! tip "Working across several establishments"
    Because a role carries a single perimeter, a user who must work in several establishments is given **several roles**, one per perimeter. This also lets you combine a *read-only* role in one perimeter with a *read-write* role in another.

!!! info "Seeing everything is reserved to administrators"
    There is no "super" perimeter that sees all the others. A user who must oversee the **entire** cartography across every establishment is either an **administrator**, or holds a role in each perimeter.

## The working perimeter (selector)

When a user is responsible for **more than one perimeter**, Mercator displays a **perimeter selector** in the UI (just above the search field).

### What the selector does

The selector shows:

- An entry for **all your perimeters** (no filtering)
- Each perimeter you have a role in

Selecting a perimeter has two effects:

1. **Filters** the current page to show only objects in that perimeter
2. Becomes the **default perimeter** for any new object you create

### Selector behavior

| User scenario | What they see |
|---|---|
| Single perimeter (1 role) | No selector (perimeter name shown as text) |
| Multiple perimeters (multiple roles) | Dropdown selector with all accessible perimeters |
| No perimeter management enabled | No selector at all |
| Administrator | Sees all objects (no filtering) |

!!! info "Changing perimeter reloads the page"
    When you switch the working perimeter, the current page reloads. Any unsaved form data is lost. If the current record falls outside the new perimeter, the page displays "not found" (403 Forbidden).

!!! note "The current choice is kept for the session"
    Your selected perimeter persists for the duration of your login session. When you log back in, it resets to the default (all perimeters or the single perimeter).

## Creating and moving objects between perimeters

### When creating an object

If you have **more than one perimeter**, a **Perimeter** dropdown appears in the create form (before the Name field).

- It defaults to your **current working perimeter**
- You can only select perimeters you have access to
- Validation enforces that the selected perimeter is writable

### When editing an object

If you have **more than one perimeter**, the **Perimeter** dropdown shows the object's current perimeter.

You can:

- **View** the object's perimeter assignment
- **Move** it to another perimeter (if you have write access to both)
- The name must remain unique within the target perimeter

After moving, the object's audit log records the change.

### Editing across your perimeters

You can only edit objects in perimeters you have a **role with write permissions** for. Read-only roles do not allow moving or reassigning objects.

Example:

- Role "Admin" in perimeter 1 → can move objects in perimeter 1
- Role "Viewer" in perimeter 2 → cannot move objects in perimeter 2 (read-only)

### Object identity in the UI

Once an object is moved to a non-default perimeter, its identity is displayed as:

```
[Perimeter Name] / Object Name
```

Example: `[Branch A] / prod-db-01` to quickly show which perimeter it belongs to.

!!! info "You can only use your own perimeters"
    A user can only assign an object to a perimeter they are responsible for. This is enforced when the form is saved, not only in the drop-down list.

!!! tip "The same name can be reused across perimeters"
    Object names are unique **within a perimeter**. Two different establishments can each have an object called, for example, *"DNS Server"*, without any conflict.

## Application flows and physical links across establishments

### What are cross-perimeter flows?

A flow can connect two objects that belong to **different perimeters**. This allows you to **identify the interactions between establishments** while keeping each establishment's object list separate.

Example:

```
Head Office DNS Server (Perimeter 1)
    ↓ resolves for ↓
Branch A Mail Server (Perimeter 2)
```

### Who sees a cross-perimeter flow?

Application flows and physical links **connect two objects that may belong to different perimeters**. Rather than belonging to a single perimeter, such a link is **visible from every perimeter it touches**.

A flow is **visible** to users in **any perimeter it touches**:

- Users with roles in Perimeter 1 see the flow (from Head Office's perspective)
- Users with roles in Perimeter 2 see the flow (from Branch A's perspective)
- Users in other perimeters do NOT see the flow

This is what makes it possible to **identify the flows between applications of different establishments**: the same flow appears to the teams of both establishments, each seeing it from their own side.

### Local vs. remote objects

In a flow connecting perimeters, you see:

- **Local objects** (in your current working perimeter or visible through your roles)
  → shown in **full detail** (name, type, all attributes)

- **Remote objects** (in a perimeter you don't manage)
  → shown as a **reference card** (name + perimeter tag)
  → you cannot edit remote objects directly
  → clicking them does NOT navigate to the full record (403 forbidden)

Example (for a Head Office admin):

```
My DNS Server (full detail, editable)
    ↓ resolves for ↓
[Branch A] / Mail Relay (reference card, read-only)
```

!!! info "Records from another perimeter appear as a reference"
    When a flow points to an object located in a perimeter you do not manage, that remote object is shown as a **short reference** (its name and perimeter) rather than its full record, so the flow remains readable without exposing the other establishment's details.

### Creating flows across perimeters

You can create a flow to any object you can **see**:

- Objects in perimeters you manage
- Objects you are assigned as a **cartographer**
- Remote objects (only if you have a read role in that perimeter)

However, **at least one endpoint must be in a perimeter you can edit**. You cannot create an orphaned flow between two perimeters you only have read access to.

### Audit and ownership

The flow's creator and creation date are recorded. The flow is owned by the creator's primary perimeter (the first perimeter they manage, or the one where the primary endpoint lives).

!!! info "Flows are not deleted when you delete a perimeter"
    If you delete a perimeter, its objects are deleted, but flows pointing to those objects remain (with broken references). Plan your deletions carefully.

## Importing data with perimeters (GLPI)

When Mercator syncs data from GLPI, imported objects are automatically assigned to a **target perimeter** specified in the connector configuration.

### Configuration

In the GLPI connector settings:

1. Select a **Target Perimeter** (dropdown of available perimeters)
2. Leave blank to use the **default perimeter** (id = 1)
3. Save

All objects imported or updated from that GLPI instance will have the specified `perimeter_id`.

### Reconciliation

The GLPI connector uses **two keys** to match existing objects:

- **GLPI ID** (stored as `[glpi_id:NNN]` in the object description)
- **Perimeter** (from the connector's configuration)

This means:

- Same GLPI ID in different perimeters = **different Mercator objects**
- Same name in different perimeters = **different Mercator objects** (names are unique per perimeter, not globally)

### Re-import behavior

**When you run the sync again:**

1. **Existing objects are updated in place** (non-destructive sync)
   - Same GLPI ID + same perimeter → update existing record
   - New GLPI ID or new perimeter → create new record

2. **If you change the connector's target perimeter:**
   - Objects already imported stay in their original perimeter
   - Newly imported objects land in the new perimeter
   - Result: Objects from the same GLPI instance spread across multiple perimeters

3. **To move imported objects to a different perimeter:**
   - Manually edit each object in Mercator and reassign the perimeter, OR
   - Delete the connector, change the target perimeter, and re-run the sync (this creates duplicates, so not recommended)

!!! tip "Keep GLPI → Mercator perimeter mapping stable"
    Decide on your GLPI-to-Mercator perimeter mapping **before** the first import. If you need to change it later, plan the migration carefully to avoid spreading objects across unintended perimeters.

## Security: Perimeter boundaries and access control

### Principle: Users see what they're responsible for

By default, users see and can modify only objects in perimeters they have a role assigned for. Perimeter boundaries are enforced at every layer:

- Query-level (automatic scope filtering)
- Policy-level (authorization gates)
- Form validation (can only assign objects to your perimeters)

### What users cannot do

- **View** objects outside their assigned perimeters (except remote references in flows)
- **Edit, move, or delete** objects outside their assigned perimeters
- **Create a flow** to an object in a perimeter they can't access
- **Assign** an object to a perimeter they don't have write access to
- **Access perimeter management** (only admins)

### Enforcement

```php
// Query scope: Automatic filtering on every fetch
Object::whereIn('perimeter_id', $user->rolePerimeters())
      ->get();

// Policy gate: Before any write
Gate::denies('update', $object) 
    // Checks: is object in one of user's perimeters?
    abort_if(..., 403);

// Form validation: On create/update
$request->validate([
    'perimeter_id' => 'in:' . implode(',', $user->rolePerimeters())
]);
```

### Cartographer assignments

A **cartographer** is responsible for a specific individual object, regardless of perimeter. A cartographer:

- **Always sees** their assigned object (even if in another perimeter)
- Can **edit** the object if their role grants write permissions in that perimeter
- Cannot move the object to another perimeter (unless they also have a role there)

### Audit trail

Every object move between perimeters is logged:

- **Who** moved it (user + role)
- **When** (timestamp)
- **What** (object name + old perimeter → new perimeter)

Admins can review changes in the **Audit Log** (Administration → Logs).

## Best practices

### Before enabling perimeter management

1. **Identify your establishments/units**
   - Each autonomous, self-managed unit = 1 perimeter
   - Avoid creating perimeters for organizational convenience (e.g., by team or function)
   - Don't create "empty" perimeters in anticipation

2. **Plan your role structure**
   - Map each team/user to the perimeters they manage
   - Decide on permission levels (Admin, Editor, Viewer, etc.)
   - Identify shared roles (e.g., "Global Read-Only" for executives)
   - See the *Roles* documentation for role setup

3. **Prepare the default perimeter name**
   - Rename the default perimeter (id = 1) to match your primary establishment (e.g., "Head Office")
   - This is the only perimeter that cannot be deleted

### Activate incrementally

1. **Enable** the feature (Administration → Configuration → Perimeters)
2. **Rename** the default perimeter if needed
3. **Create** new perimeters for each establishment
4. **Move** existing objects to their correct perimeters
5. **Create** or update roles and assign users (see *Roles* documentation)
6. **Test** with a pilot team before full rollout

### Moving objects safely

Before moving an object to a different perimeter:

1. Check that the object's **name doesn't already exist** in the target perimeter
2. Verify that any **flows pointing to it** are still valid (flows auto-adapt)
3. Review the **audit trail** to understand the object's history
4. Notify any affected teams (especially if it affects their flows)

### Deleting a perimeter safely

1. **Audit** what's in the perimeter
   - List all objects (Administration → Perimeters → [perimeter] → Objects)
   - List all roles (Administration → Perimeters → [perimeter] → Roles)

2. **Migrate data**
   - Move all objects to another perimeter or the default
   - Reassign all roles to another perimeter or the default

3. **Delete** the perimeter
   - Administration → Configuration → Perimeters → Delete
   - Confirm (deletion is permanent)

4. **Verify** the migration completed
   - Check that no objects or roles still reference the deleted perimeter

### When NOT to use perimeters

Perimeters are designed for **organizational separation** (multiple establishments, subsidiaries, business units). They are **not** ideal for:

- **Temporary grouping** (use tags or labels instead)
- **Team-level filtering** (use roles and permissions)
- **Object categorization** (use types, tags, or a taxonomy)

If you don't have multiple autonomous establishments, leave perimeter management **disabled**. It adds complexity with no benefit.

## Troubleshooting

### "I enabled perimeter management but nothing changed"

✓ **Expected behavior.** All objects are still in the default perimeter (id = 1).  
✓ Create new perimeters and move objects to partition the cartography.  
✓ Assign roles to users in each perimeter (see *Roles* documentation).  

### "I can't move an object to a different perimeter"

Possible reasons:

- You don't have a **write role** in the target perimeter (only read roles allowed)
- The target perimeter already has an object with the same name
- You are trying to move an object you are assigned as a **cartographer** (ask an admin)

**Fix:** Create or request a write-access role in the target perimeter first (see *Roles* documentation).

### "A user can't see objects they should be able to see"

Check:

1. The **user's roles** (Administration → Users → [user] → Roles)
   - Verify the roles are assigned to the correct perimeters
   - Verify the roles have read or write permissions (see *Roles* documentation)

2. The **object's perimeter** (open the object, check the Perimeter field)
   - Verify the object is in one of the user's perimeters

3. The **cartographer assignments** (if the object is isolated)
   - Check if the user is listed as a cartographer


### "A flow disappeared after I moved an object"

Flows are **not deleted** when you move an object. They are preserved and remain visible to users in the affected perimeters. If the flow seems to have disappeared:

1. Check that you still have a role in the perimeter where the flow originates
2. Reload the page (cache may be outdated)
3. Check the audit log to see if the flow was explicitly deleted


