# Refill Tracker

Track unopened spares for household supplies and see which items need restocking. An item goes on the Restock list when its quantity is at or below its threshold. Use a threshold of zero to list it only when the last spare is gone.

## Deployment

1. Deploy this app alongside `1-Framework` and `user-management` on the same HTTPS host.
2. Register the `refill-tracker` project at its deployed path in User Management. Grant Member access to people who need to add or change their inventory, or Viewer access to read their own inventory.
3. Create a dedicated private directory outside the document root, writable by the PHP account with permissions restricted to that account. Copy `config.local.example.php` to the ignored `config.local.php`, and set `storage_path` to that directory's absolute path. Keep the directory in server backups.
4. Open the deployed project path and sign in with User Management.

Each account gets one framework JSON file keyed by the hash of its stable User Management ID. The framework creates the file on the first saved change and locks each read-modify-write transaction. The app derives the Restock list on each request.

## Manual verification

- Add toothpaste with one spare and a threshold of one. Verify it appears under Restock. Add a spare and verify it clears. Remove two spares using count correction and confirm it returns at zero.
- Edit the unit and threshold and verify that the spare count is preserved. Refresh after a successful change and verify it is applied only once.
- Open the app with a different Member account and confirm its list starts empty. Verify a Viewer sees no editing controls and a signed-in Member submitting an invalid form token receives HTTP 403.
- Point the configuration to a directory inside the document root and verify the app reports it is unavailable. Restore the external private path before use.

## Storage, usage, and order timing

Add the same item to multiple storage locations from its inventory card using Log refill or Correct count. Move spares to transfer stock without changing the household total. Older records start with their existing balance under **Unassigned** and can be distributed using Move spares.

Choose **Opened or replaced one** to subtract one from the source storage location and log the current time and the room where it will be used. Stock corrections and moves leave the opening history intact. Room and storage inputs suggest names already used in your inventory.

Two openings at different times establish an initial average interval. The app shows the household average and room-specific averages in Opening history. The suggested order date estimates when the last spare will be opened, minus a fixed three-day lead time, and shows today when that date has passed. Use the estimate when choosing a delivery schedule. The app records opening times and calculates estimates without placing orders.

### Location and usage checks

- Add two spares to the hall closet and three to the bathroom cabinet. Move one between them and verify the total stays five.
- Log an opening in a bathroom and verify only the selected source drops by one. Check that the history shows both locations. Correct a count and verify the opening history is unchanged.
- Check that two openings at different times produce a suggested date and that room-specific intervals use only openings for that room.

## Who used or refilled an item

Use **Opened or replaced one** when someone starts a new carton of milk, opens a box of tissues, or replaces a toothbrush. Enter who used it, where it was used, and where the spare came from. This action tracks one whole unit, such as a carton, roll, bottle, or toothbrush. Set the unit when adding each item. Use the same workflow for frequent and occasional replacements.

Use **Log refill** when someone adds spares. Enter their name, the quantity, and the storage location. The refill history shows these details and the time. Select **Correct count** for a bookkeeping adjustment. Corrections and stock transfers leave both histories alone. Names are household labels, and previously used names appear as suggestions. Older opening entries show that the person was not recorded.

## Starter and custom items

Select **Add starter items** to populate the household starter list. It includes separate 2% and 4% milk entries, toilet paper, toothpaste, Kleenex / facial tissues, paper towels, contact solution, a toothbrush, deodorant, and dish soap. Starter entries begin with no assigned stock and join the restock list once you record stock and reach the threshold. Repeating the action skips existing starter entries, including ones you renamed, and matches existing item names case-insensitively.

Use **Add item** for anything else. Use **Edit** to change the name, unit label, details (such as brand or package size), and restock threshold. Renaming an item keeps its existing storage balances and activity history.

## Purchase details

On **Log refill**, expand **Purchase details** to enter the seller, units per pack, and total price in USD. For one Amazon two-pack of toothpaste, enter quantity 1 and pack size 2. For two Costco 48-packs of toilet paper, enter quantity 2 and pack size 48. Inventory goes up by 2 tubes or 96 rolls respectively, and refill history shows the pack breakdown, seller, total price when entered, and price per unit. Leave pack size blank to enter individual units. Use **Correct count** for adjustments in individual units.
