# Monarc Integration

This page describes the integration between Mercator and [MONARC](https://www.monarc.lu) (Optimised Risk Analysis Method), the open source risk analysis tool developed by [NC3 Luxembourg](https://www.nc3.lu), compliant with the ISO/IEC 27005 standard. It allows you to generate a risk analysis from the information system cartography, and then synchronise it directly to a MONARC Front Office instance.

The cartography objects are mapped to the asset types of the public [MOSP](https://objects.monarc.lu/) repository (MONARC Objects Sharing Platform), which provides the knowledge base (assets, threats, vulnerabilities, risks and security referentials) used to build the analysis.


## Enabling and configuring the Monarc connection

The connection to a MONARC Front Office instance is configured in **Configuration → Monarc tab**.

| Field | Description |
|-------|-------------|
| Enable Monarc integration | Displays the "Monarc" entry in the Tools menu and grants access to the generation screen |
| Monarc instance URL | Base URL of the MONARC Front Office instance |
| Login | Account used to authenticate on the Monarc API |
| Password | Leave empty to keep the previously saved password |

A **Test connection** button lets you verify that authentication works before generating an analysis.

📢 *These values can also be pre-filled via the `MONARC_URL` and `MONARC_LOGIN` environment variables (the password can only be entered from the configuration screen).*

This same page displays the status of the link with the Monarc risk analysis currently being tracked (see [Resetting the link](#resetting-the-link)).


## Accessing the generation screen

The risk analysis generation screen is accessible via the **Tools → Monarc** menu, only when the integration is enabled and for users with the reports access permission.


## Building the analysis

### 1. Analysis model

| Field | Description |
|-------|-------------|
| Analysis name | Combined field: select a risk analysis that already exists in Monarc, or enter a new name to create one |
| Description | Free-text description of the analysis |
| Language | Language of the risk analysis (French or English) |
| Analysis model | Monarc model used only when creating a new risk analysis |

When an existing risk analysis is selected, Mercator queries Monarc to retrieve its language and automatically rebuilds the object selection below based on what has already been sent to it.

⚠️ *Linking a risk analysis that was not created from Mercator may duplicate objects during the first synchronisation: Mercator cannot know what the risk analysis already contains if it was not populated by Mercator itself.*

### 2. Security referentials (optional)

Select one or more referentials published on MOSP (e.g. ISO 27002, ISO 27017) to include their security controls in the export.

### 3. Selecting cartography objects

For each cartography view (Ecosystem, Information System, Administration, Logical Infrastructure, Physical Infrastructure), a table lists one row per Monarc asset type encountered:

| Column | Description |
|--------|-------------|
| Matching Mercator objects | Mercator object family/families associated with this asset type |
| Asset type | MOSP asset code |
| Monarc object | Label of the asset in the user's language |
| Risks | Number of threat/vulnerability pairs (AMV) associated with this asset; clicking the number displays the details |
| Generic type | Adds a single generic object for the entire asset type, instead of individual Mercator objects |
| Mercator objects | Multiple selection of the Mercator objects (application, process, server…) to include in the analysis |

Checking **Generic type** disables the row's object list (without clearing an existing selection) and adds a single object representing the whole category.

### 4. Actions

| Button | Effect |
|--------|-------|
| Save | Saves the current selection without generating a file or contacting Monarc |
| Export JSON | Downloads the built analysis in JSON format, which can be imported manually into Monarc |
| Clear | Resets the entire selection |



## Direct synchronisation with Monarc

In addition to the JSON export, the **Monarc risk analysis synchronisation** card allows you to create or update the risk analysis directly via the Monarc API, without going through a file:

- if the risk analysis does not yet exist in Monarc, it is created from the selected analysis model;
- only the Mercator objects (and their risks) that have **not already been sent** during a previous synchronisation are imported.

For each linked risk analysis, Mercator keeps an up-to-date list of the objects already synchronised. Repeating a synchronisation without changing the selection therefore sends nothing new ("Already up to date").

⚠️ *Deletions or modifications made in Mercator are never propagated to Monarc during a resynchronisation: only additions are synchronised.*

The link status is shown above the synchronisation button:

- the currently linked risk analysis (number and name), or the absence of a link;
- the date of the last synchronisation;
- the number of objects already tracked for this risk analysis.

A warning message is displayed if the linked risk analysis has been deleted directly in Monarc, or if no analysis model could be retrieved.


## Resetting the link

The **Reset link** button is located in **Configuration → Monarc tab**.

This action only removes the local link kept by Mercator (the tracked risk analysis and the list of objects already sent): the next synchronisation will create a brand new risk analysis. **The risk analysis and its content are never deleted in Monarc.**


## Summary table

| Feature | Location | Required permission |
|---------|----------|---------------------|
| Enable/configure the Monarc connection | Configuration → Monarc tab | `configure` |
| Test the connection | Configuration → Monarc tab | `configure` |
| Reset the link | Configuration → Monarc tab | `configure` |
| Generate / export / synchronise an analysis | Tools → Monarc | `reports_access` |