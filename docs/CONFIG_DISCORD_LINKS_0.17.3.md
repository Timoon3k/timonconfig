# Discord links in Guide and Values — theme 0.17.3

Paste Discord URLs directly into a configuration's Guide or Values field. When an allowed reader opens that field, message, channel and invite links become clickable and open in a separate tab. Existing configurations benefit on their next view; no content migration or resave is needed.

Supported examples:

```text
https://discord.com/channels/1557854161805115402/1560781856931315832
https://discord.com/channels/SERVER_ID/CHANNEL_ID/MESSAGE_ID
https://discord.com/invite/YOUR_INVITE
https://discord.gg/YOUR_INVITE
discord.gg/YOUR_INVITE
```

Legacy `discordapp.com` and the official `ptb`/`canary` hosts are also recognized. Private messages and channels still require the reader's own Discord login and channel permissions.

The change runs only on text nodes inside the already loaded Guide/Values dialog. It preserves Guide formatting and existing links, keeps punctuation outside link destinations, and does not execute Values as HTML. Stored fields, Copy content output, Matrix TXT exports and uploaded files remain unchanged. The website does not fetch Discord messages or require an additional bot token for clickable links. Existing field visibility and membership checks still apply.

Install `artifacts/timonconfigs-theme-0.17.3.zip` as a theme update, purge LiteSpeed/page/CDN cache once and reload an existing configuration. Keep Core 1.3.2 for the preceding download fix. No setup or Rank Math import is needed. GitHub publication does not establish installation on WordPress hosting.

Local browser coverage uses real WordPress content responses and intercepted test destinations, including Guide and Values, desktop/mobile, exact copy behavior, denied access, existing anchors and invalid/lookalike destinations. Release results are recorded in `PROGRESS.md`.
