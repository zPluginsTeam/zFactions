
# 🛡 zPlugins Factions — Advanced Factions System for PocketMine-MP

> **A fully-featured factions plugin for PocketMine-MP 5.x**, built for performance and simplicity  
> by **zPluginsTeam**

---

## ⚔️ Features

- 🏘 **Faction Creation & Management**
- 🤝 **Invites, Promotions, Demotions**
- 💬 **Faction & Ally Chat Systems**
- 🌍 **Territory Claiming, Overclaiming & Unclaiming**
- 🏡 **Faction Home & Set Home**
- 🏦 **Faction Bank System (Deposit & Withdraw)**
- 🗺 **Territory Map Viewer**
- 🛡 **Alliances, Enemies, and Diplomacy**
- ✏️ **Faction Description & Renaming Support**

---

## 📦 Requirements

- [x] PocketMine-MP 5.0.0+
- [x] [BedrockEconomy](https://poggit.pmmp.io/p/BedrockEconomy) (required for economy features)

---

## 📥 Installation

1. Download the `zFactions.phar`
2. Place it in your `plugins/` folder
3. Restart your server
4. Configure settings in `config.yml` if available
5. Done!

---

## 🧾 Commands

| Command | Description |
|--------|-------------|
| `/f create <name>` | Create a new faction |
| `/f invite <player>` | Invite a player to your faction |
| `/f accept <faction>` | Accept a faction invite |
| `/f deny <faction>` | Deny a faction invite |
| `/f promote <player>` | Promote a faction member |
| `/f demote <player>` | Demote a faction member |
| `/f rename <name>` | Rename your faction |
| `/f description <text>` | Set a new description for your faction |
| `/f leave` | Leave your current faction |
| `/f claim` | Claim the current chunk |
| `/f overclaim` | Overclaim territory from another faction |
| `/f unclaim` | Unclaim the current chunk |
| `/f unclaimall` | Unclaim all your faction’s territory |
| `/f home` | Teleport to faction home |
| `/f sethome` | Set your faction home |
| `/f bank` | View the faction bank balance |
| `/f deposit <amount>` | Deposit money into your faction’s bank |
| `/f withdraw <amount>` | Withdraw money from your faction’s bank |
| `/f chat` | Toggle faction-only chat |
| `/f allychat` | Toggle chat with allies |
| `/f fly` | Toggle faction flight mode |
| `/f info [faction]` | View info about a faction |
| `/f map` | View the faction territory map |
| `/f ally <faction>` | Request alliance with another faction |
| `/f acceptally <faction>` | Accept an alliance request |
| `/f denyally <faction>` | Deny an alliance request |
| `/f enemy <faction>` | Declare another faction as an enemy |
| `/f help` | View the help message |

---

## 📜 Permissions (Optional)

| Permission | Description | Default |
|------------|-------------|---------|
| `zfactions.command` | Use basic faction commands | `true` |
| `zfactions.admin` | Admin-only controls and overrides | `op` |
| `zfactions.fly` | Allows flying in your faction claim | `true` |
| `zfactions.overclaim` | Allows overclaiming faction claims | `true` |
