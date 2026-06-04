# WP Mojibake Fixer (IT & DE)

A lightweight WordPress plugin designed to fix character encoding issues (Mojibake) that often occur after migrating a database (e.g., using Duplicator, All-in-One WP Migration, or manual SQL imports) between servers with conflicting Charset/Collation settings. 

Tailored specifically to fix **Italian accents** (à, è, ì, ò, ù) and **German characters** (Umlauts and Eszett: ä, ö, ü, ß), as well as common typographic symbols (smart quotes, en-dashes, copyright symbols).

Developed by [Riccardo Di Curti](https://riccardodicurti.it).

## 🚀 Features

* **One-Click Fix:** Performs a precise Search & Replace operation directly on the `wp_posts` and `wp_postmeta` tables.
* **Smart Word Replacement:** Targets specific trailing characters (like broken "à") in full words to prevent corrupting other characters during the fix.
* **Page Builder Compatible:** Modifies `wp_postmeta` ensuring compatibility with Elementor, ACF, Divi, WPBakery, and other meta-based plugins.
* **Debug Panel:** Displays your `wp-config.php` constants alongside your actual MySQL server variables (Client, Server, Connection) to help you identify the root cause of the encoding mismatch (e.g., `utf8mb4` vs `latin1_swedish_ci`).

## ⚠️ Important Warning

**ALWAYS back up your database before using this plugin.** 
The plugin modifies the database directly using SQL `REPLACE` queries. Changes are irreversible.

## 📦 Installation

1. Download the plugin as a `.zip` file or clone this repository.
2. Log in to your WordPress Admin dashboard.
3. Go to **Plugins > Add New > Upload Plugin**.
4. Choose the zip file and click **Install Now**.
5. Click **Activate Plugin**.

## 🛠️ Usage

1. After activation, navigate to **Tools > Fix Mojibake IT/DE** in your WordPress dashboard.
2. Review the **Debug** section at the bottom if you want to understand why the migration broke your characters.
3. Click the **Run Database Fix** button.
4. Clear your website cache (if you use any caching plugins) and check your frontend. The broken characters should now be restored.
5. *(Optional but recommended)* Once the texts are fixed, you can safely deactivate and delete the plugin to keep your installation clean.

## 🐛 Common Causes for Mojibake

This issue typically arises when:
- The source database was exported in `UTF-8`.
- The destination server's MySQL configuration defaults to `latin1` (or `latin1_swedish_ci`).
- The migration tool (like Duplicator) injects the `UTF-8` data through a `latin1` connection, causing a double-encoding glitch that permanently saves symbols like `Ã¨` or `Ã¼` in the database.

## 🧑‍💻 Contributing & Extending

The plugin maps specific broken strings to their correct values. If you need to add support for other languages (like French or Spanish), you can easily modify the `$mapping` array inside the `wp_mojibake_fixer_execute()` function in `wp-mojibake-fixer.php`.

## 📄 License

This project is licensed under the GPL-2.0+ License.

---
*Created by [Riccardo Di Curti](https://riccardodicurti.it).*
