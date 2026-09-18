# Roles in Mercator

Role is the main mechanism to grant list, read and write access to objects in Mercator. This documentation explains what a role is, how to create and manage roles, and how they work with perimeters to control user access.

## Introduction — What is a role?

A **role** is a set of **permissions** that controls what a user can do in Mercator:

- **View** (read) — list and see details of objects
- **Create** — add new objects of a specific type
- **Edit** — modify existing objects
- **Delete** — remove objects

Each user can hold **one or more roles**, and roles can be assigned to perimeters to partition access by establishment.

!!! info "Three mechanisms for access control"
    Mercator combines three complementary notions to control what users can do:
    
    - **Role** defines *what* a user can do (view, create, modify, delete which categories of objects)
    - **Perimeter** defines *which* establishment's objects those rights apply to (see the *Perimeters* documentation)
    - **Cartographer** assignment delegates responsibility for **specific individual objects**, regardless of perimeter or role (see the *Cartographers* documentation)
    
    The three work together to give you fine-grained access control.

## The permission model

### Permission categories

Mercator organizes permissions by **object type** and **action**:

- **Servers** → View, Create, Edit, Delete
- **Applications** → View, Create, Edit, Delete
- **Networks** → View, Create, Edit, Delete
- **Sites** → View, Create, Edit, Delete
- ... (one category per object type)

### Built-in role templates

Mercator provides standard role templates to simplify setup:

| Role | Description | Typical permissions |
|------|-------------|-------------------|
| **Admin** | Full access to all object types and perimeter management | All permissions |
| **Editor** | Can view, create, and edit objects (not delete) | All CRUD except Delete |
| **Viewer** | Read-only access to all objects | View only |
| **Operator** | Manage servers and physical infrastructure | Create/Edit Servers, Sites, Physical objects |

!!! note "Administrators"
    **Administrators** have special status. They are never filtered by perimeter and always see the entire cartography, regardless of how perimeters are configured. Administrator roles are typically assigned to IT directors or system managers.

## Creating and managing roles

### Create a new role

1. Go to **Administration → Roles**
2. Click **+ Create Role**
3. Enter a **role name** (e.g., "Server Administrator", "Read-Only Audit")
4. Select a **perimeter** (defaults to the default perimeter, id = 1)
   - *See the Perimeters documentation for details on perimeters*
5. Check the **permissions** you want to grant:
   - For each object type, select: View, Create, Edit, Delete
6. Click **Create**

### Edit an existing role

1. Go to **Administration → Roles**
2. Find the role in the list
3. Click **Edit**
4. Change the **name**, **perimeter**, or **permissions**
5. Click **Save**

### Delete a role

1. Go to **Administration → Roles**
2. Find the role in the list
3. Click **Delete**
4. Confirm

!!! warning "Before deleting a role"
    Check how many users are assigned to this role. Deleting it removes their access. Consider reassigning users to another role first.

## Assigning roles to users

### Assign roles to a user

1. Go to **Administration → Users**
2. Find the user in the list
3. Click **Edit**
4. Under **Roles**, check the roles you want to assign
   - A user can have multiple roles (for access to multiple perimeters)
5. Click **Save**

### Remove a role from a user

1. Go to **Administration → Users**
2. Find the user
3. Click **Edit**
4. Under **Roles**, uncheck the role
5. Click **Save**

### A user with multiple roles

A user may hold roles in multiple perimeters. The permissions are **combined**:

```
User "Alice" has:
  - Role "Admin" in Perimeter 1 ("Head Office")
    → Full access to all Head Office objects
  
  - Role "Viewer" in Perimeter 2 ("Branch A")
    → Read-only access to all Branch A objects

Result: Alice sees and can edit objects in Perimeter 1,
        and view-only objects in Perimeter 2
```

The **perimeters the user can access** are the union of all their roles' perimeters.

!!! tip "Mix permissions across perimeters"
    A user can have a read-only role in one perimeter and a full-access role in another. This lets you balance access carefully.

## Built-in admin role

The **Administrator** role is special:

- Has **all permissions** by default
- Is **never filtered by perimeter** — sees the entire cartography
- Has access to **Administration** features
- Cannot be deleted (always available)
- Should only be assigned to trusted IT staff

Administrators are responsible for:

- Creating and managing other roles
- Assigning roles to users
- Managing perimeters
- Auditing access and changes
- Handling user requests for access changes

!!! warning "Keep administrator access restricted"
    Only assign the Administrator role to people who genuinely need full system access. For most users, create specific roles with limited permissions.

## Role and perimeter relationship

Each role is attached to **exactly one perimeter**. The role's permissions apply only to objects within that perimeter.

### Example: Multi-establishment setup

Your organization has two sites: Head Office and Branch A. You want:

- Head Office staff to manage all Head Office objects
- Branch A staff to manage only Branch A objects
- Executives to view both sites (read-only)

**Solution:**

1. Create two perimeters:
   - Perimeter 1: "Head Office"
   - Perimeter 2: "Branch A"
   - (See *Perimeters* documentation for details)

2. Create roles per site:
   - Role "HQ Admin" → Perimeter 1 → Admin permissions
   - Role "Branch Admin" → Perimeter 2 → Admin permissions
   - Role "Global Viewer" → Perimeter 1 → View only
   - Role "Global Viewer" → Perimeter 2 → View only

3. Assign users:
   - HQ staff → "HQ Admin" role
   - Branch staff → "Branch Admin" role
   - Executives → Two "Global Viewer" roles (one per perimeter)

Result: Each team manages their own site, executives see both (read-only), and data is properly partitioned.

!!! info "See the Perimeters documentation"
    For a complete explanation of how perimeters work, when to enable them, and how to migrate data, refer to the *Perimeters* documentation.

## User login and access

### On first login

A new user with assigned roles:

1. Logs in with their credentials
2. Is automatically granted all permissions from their assigned roles
3. Can see all objects in the perimeters their roles grant access to
4. If multiple perimeters: sees a **perimeter selector** to switch between them

### Session and role changes

- **Role changes take effect immediately** on the next page reload
- If a role is removed while the user is logged in, they may still have cached access until they refresh or log back in
- **Admin role removal** takes effect on next login

## Best practices for roles

### 1. Keep role names clear

Use descriptive names that indicate both the level and scope:

✓ Good:
- "HQ Server Administrator"
- "Branch Read-Only Viewer"
- "Audit Inspector"

✗ Avoid:
- "Role 1"
- "Admin"
- "User Role"

### 2. Principle of least privilege

Assign users the **minimum** permissions they need:

- New staff → start with "Viewer" role
- Promote to "Editor" only when needed
- "Admin" or "Delete" permissions → only for trusted staff

### 3. Use role templates

For consistency, base new roles on built-in templates:

- Copy "Editor" and customize instead of creating from scratch
- This ensures common patterns are consistent across your organization

### 4. Document role purposes

For each custom role, document:

- **What it's for** (e.g., "Manage physical server inventory")
- **Who uses it** (e.g., "Infrastructure team")
- **When to grant it** (e.g., "On hire, after training")

This helps admins make consistent role assignments.

### 5. Review role assignments regularly

Every quarter, audit:

- **Who has Admin roles** (should be minimal)
- **Unused roles** (delete or merge them)
- **Role creep** (permissions expanded beyond original scope)
- **Orphaned roles** (assigned to no one, clean up)

### 6. Separate by concern

If possible, create roles for specific responsibilities:

- "Server Administrator" (manage servers only)
- "Network Administrator" (manage networks only)
- "General Read-Only" (view everything)

This makes it easier to grant partial access and audit who can do what.

## Troubleshooting

### "A user can't see objects they should be able to see"

Check:

1. **User's roles** (Administration → Users → [user] → Roles)
   - Is the user assigned at least one role?
   - Does that role have "View" permission for the object type?

2. **Role's perimeter** (Administration → Roles → [role])
   - Is the role assigned to the correct perimeter?
   - If using perimeters: is the object in that perimeter?

3. **Object's perimeter** (open the object, check the Perimeter field)
   - If using perimeters: is the object in one of the user's role perimeters?

4. **Cartographer assignments**
   - Is the user listed as a cartographer for this object?
   - (See *Cartographers* documentation)

**Fix:** Assign the user a role in the correct perimeter with View permission for that object type.

### "A user can't edit an object"

Check:

1. **Role permissions** (Administration → Roles → [role])
   - Does the role have "Edit" permission for this object type?

2. **Write permissions**
   - "View" role = read-only (can't edit)
   - "Edit" or "Admin" role = can edit

3. **Perimeter ownership** (if using perimeters)
   - Is the role's perimeter the same as the object's perimeter?

**Fix:** Assign the user a role with "Edit" permission in the correct perimeter.

### "I can't delete a role because it's in use"

This means users are still assigned to this role.

**Solution:**

1. Go to **Administration → Roles** and find the role
2. Click **View assignments** to see which users have it
3. Reassign those users to another role (Administration → Users)
4. Once no users are assigned, you can delete the role

### "An admin can see everything but a viewer sees nothing"

**For the viewer:**

1. Check that the viewer has at least one role assigned (Administration → Users → [user] → Roles)
2. Check that the role has "View" permission
3. If using perimeters: check that objects are in the role's perimeter

**For the admin:**

- Admins always see everything, regardless of role perimeter or permissions
- This is by design (admins manage the whole system)

### "Role changes aren't taking effect"

User may have cached permissions. Ask them to:

1. **Reload the page** (F5 or Cmd+R)
2. **Log out and log back in** (if page refresh doesn't work)

After logout/login, new role permissions take effect immediately.

