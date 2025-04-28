# SimpleQuests

A simple yet powerful quest system plugin for PocketMine-MP servers.  
Create, manage, and complete quests with live progress updates, cooldowns, and automatic saving!

## Features
- 📋 **Quest Creation**: Easily create block-breaking quests in-game with `/addquest`.
- 🛠️ **Quest Editing**: Edit existing quests via an intuitive form UI.
- 🗑️ **Quest Deletion**: Delete quests with a few clicks.
- 📈 **Progress Tracking**: Players see a live progress bar while completing quests.
- ⏳ **Cooldown System**: Set cooldowns between quest completions.
- 📝 **Auto Save**: Player progress and quest completions are automatically saved to JSON files.
- ⚡ **Simple Commands**: Easy-to-use commands for players and admins.
- 🧩 **FormAPI Integration**: Fully GUI-based menus for better user experience.

## Commands
| Command          | Description                       | Permission                        |
|------------------|-----------------------------------|----------------------------------|
| `/quest`         | Open the available quests menu    | `simplequests.command.quest`     |
| `/addquest`      | Add a new quest                   | `simplequests.command.addquest`  |
| `/editquest`     | Edit an existing quest             | `simplequests.command.editquest` |
| `/deletequest`   | Delete a quest                     | `simplequests.command.deletequest` |

## How to Use
1. Install **FormAPI** by [jojoe77777](https://github.com/jojoe77777/FormAPI) (Required).
2. Place the plugin `.phar` or folder into your `plugins/` directory.
3. Start your server.
4. Use `/addquest` to create a new block-breaking quest.
5. Players use `/quest` to view and accept quests.

## Quest Types
Currently supported quest types:
- 🧱 Break specific blocks (`BREAK_BLOCK`)

*More types (e.g., Place blocks, Kill mobs) can be added in future updates!*

## Quest Cooldowns
You can set cooldowns for quests (e.g., "1s", "5m", "2h").  
This prevents players from instantly repeating quests after completing them.

## File Structure
- `progress.json`: Stores each player's active quest progress.
- `lastCompleted.json`: Stores timestamps of quest completions to handle cooldowns.
- `config.yml`: Stores all quests and their settings.

## Dependencies
- [FormAPI](https://github.com/jojoe77777/FormAPI) by jojoe77777

## Permissions
Make sure to set the right permissions for your players/admins to control who can add, edit, or delete quests.

## Example Quest Creation
When creating a quest:
- **Name**: "Mine 10 Stone Blocks"
- **Description**: "Break stones to earn rewards."
- **Block**: `STONE`
- **Target**: `10`
- **Reward Command**: `give {player} diamond 1`
- **Reward Message**: "You've earned a diamond!"
- **Cooldown**: `10m` (optional)

---

# Download
[Latest Stable Release](https://poggit.pmmp.io/r/255464/SimpleQuests_dev-37.phar)

# Support
Having issues or suggestions? Feel free to open an issue or pull request!

---
