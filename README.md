# Mautic Bounce Webhook Bundle

A Mautic plugin that handles mailer transport webhook callbacks for bounce and spam events. It processes incoming webhook payloads from email providers and automatically marks contacts as Do Not Contact (DNC) in Mautic.

## Supported Providers

- **Mailjet** — processes `bounce`, `blocked`, and `spam` events via the [Mailjet Event API](https://dev.mailjet.com/email/guides/webhooks/)

## Requirements

- PHP 8.1 or higher
- Mautic 5.x, 6.x or 7.x

## Installation

Run the following command in your Mautic root directory:

```bash
composer require druidfi/mautic-bounce-webhook-bundle
```

Then clear the cache and go to the **Plugins** page in Mautic to install or upgrade the plugin.

## Configuration

Set the following environment variables in your Mautic `.env` or server environment:

| Variable | Required | Description |
|---|---|---|
| `MAILER_BOUNCE_PROVIDER` | Yes | The active bounce provider. Currently supported: `mailjet` |
| `MAILER_CALLBACK_SECRET` | No | A secret token to authenticate incoming webhook requests. If not set, all requests are accepted (not recommended for production). |

Example:

```dotenv
MAILER_BOUNCE_PROVIDER=mailjet
MAILER_CALLBACK_SECRET=your-secret-token
```

## Webhook URL

Configure your email provider to POST webhook events to:

```
https://your-mautic-domain.com/mailer/callback?token=your-secret-token
```

## Bounce Behaviour

| Event | Provider behaviour | Mautic action |
|---|---|---|
| Hard bounce | `bounce` with `hard_bounce: true` | Contact added to DNC (Bounced) |
| Soft bounce | `bounce` with `hard_bounce: false` | Ignored |
| Blocked | `blocked` | Contact added to DNC (Bounced) |
| Spam complaint | `spam` | Contact added to DNC (Unsubscribed) |

## Adding a New Provider

1. Create a class implementing `BounceProviderInterface`
2. Add it as a constructor argument in `BounceProviderRegistry`
3. Register it in the `$providers` array with a matching key
4. Set `MAILER_BOUNCE_PROVIDER` to the new key

## License

GPL-3.0-only
