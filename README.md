# Paystack Plugin for Shopware 6

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Shopware](https://img.shields.io/badge/Shopware-6.6%2B-blue.svg)](https://shopware.com)
[![Payment](https://img.shields.io/badge/Payment-Paystack-blueviolet.svg)](https://paystack.com)

![Shopware Paystack Logo](src/Resources/config/shopware.png)

A modern **Shopware 6 payment plugin** that integrates **Paystack**, enabling merchants across Africa to accept secure online payments via multiple channels.

Developed with ❤️ by [Kommandhub Limited](https://kommandhub.com)

---

# Table of Contents

* [Features](#features)
* [Supported Payment Channels](#supported-payment-channels)
* [Requirements](#requirements)
* [Installation](#installation)

   * [Via Composer](#via-composer-recommended)
   * [Manual Installation](#manual-installation-github-upload)
* [Configuration](#configuration)
* [Directory Structure](#directory-structure)
* [Payment Flow](#payment-flow)
* [Development & Testing](#development--testing)
* [Compatibility](#compatibility)
* [Troubleshooting](#troubleshooting)
* [Support](#support)
* [License](#license)

---

# Features

* Seamless integration with **Shopware 6 checkout**
* Secure **Paystack hosted payment page**
* Automatic **order transaction status updates**
* Built-in **sandbox mode** for testing
* Optional **debug logging** for troubleshooting
* Stores Paystack **transaction references and metadata**
* Clean and native **Shopware payment method integration**

---

# Supported Payment Channels

Depending on your Paystack configuration:

* Debit & Credit Cards
* Bank Transfers
* USSD
* QR Codes
* Mobile Money (Ghana, Kenya, etc.)

---

# Requirements

* **Shopware**: `~6.6.0` or `~6.7.0`
* **PHP**: `^8.2`
* **Paystack Account**: [https://dashboard.paystack.com/#/signup](https://dashboard.paystack.com/#/signup)
* **Composer**

---

# Installation

## Via Composer (Recommended)

```bash
composer require kommandhub/paystack-sw
bin/console plugin:refresh
bin/console plugin:install --activate KommandhubPaystackSW
bin/console cache:clear
```

---

## Manual Installation (GitHub Upload)

1. Download ZIP from your repository

2. Ensure correct structure:

   ```
   KommandhubPaystackSW.zip
   ├── src/
   ├── composer.json
   ```

3. Upload via:

   **Administration → Extensions → My Extensions → Upload Extension**

4. Install and activate plugin

---

# Configuration

Navigate to:

**Administration → Extensions → My Extensions → Paystack → Configuration**

---

## Live Mode

* Enter **Live Secret Key** (`sk_live_...`)

---

## Sandbox Mode

* Enable sandbox
* Enter **Test Secret Key** (`sk_test_...`)

---

## Debugging

Enable logging to write detailed logs to:

```
var/log/
```

Recommended for troubleshooting.

---

## Activate Payment Method

1. Go to:

   ```
   Settings → Shop → Payment Methods
   ```

2. Enable **Paystack Payment**

3. Assign it to your **Sales Channel**

---

# Directory Structure

The plugin follows a feature-based structure that keeps Shopware entry points thin and groups behavior by responsibility:

```text
KommandhubPaystackSW/
├── src/
│   ├── KommandhubPaystackSW.php
│   ├── Core/
│   │   ├── Config/
│   │   ├── Exception/
│   │   └── Util/
│   ├── Payment/
│   │   ├── Application/
│   │   │   ├── Processor/
│   │   │   └── Service/
│   │   ├── Domain/
│   │   │   ├── Event/
│   │   │   ├── Enum/
│   │   │   └── ValueObject/
│   │   └── Infrastructure/
│   │       ├── Paystack/
│   │       │   ├── Api/
│   │       │   └── Http/
│   │       └── Shopware/
│   │           ├── Handler/
│   │           └── Struct/
│   ├── Webhook/
│   │   ├── Application/
│   │   ├── Domain/
│   │   └── Presentation/
│   │       ├── Controller/
│   │       └── Listener/
│   ├── Administration/
│   │   └── Controller/
│   ├── Storefront/
│   │   ├── Controller/
│   │   └── Validation/
│   └── Resources/
│       ├── config/
│       ├── snippet/
│       ├── views/
│       ├── app/administration/
│       └── app/storefront/
└── tests/
    ├── Integration/
    └── Unit/
```

## What each area is for

* `Core/`: shared config, exceptions, and utility code used by multiple features.
* `Payment/`: payment initialization, verification, finalization, refund processing, and Paystack API adapters.
* `Webhook/`: webhook event parsing, signature validation, event listeners, and webhook-specific flows.
* `Administration/`: backend controllers used by Shopware Admin.
* `Storefront/`: storefront controllers and validation helpers.
* `Resources/`: plugin configuration, Twig templates, snippets, and frontend assets.
* `tests/`: unit and integration tests that mirror the production structure.

## Important conventions

* Feature code stays grouped by responsibility instead of by framework type alone.
* Controllers remain thin and delegate to application services.
* Test folders mirror `src/` so it is easy to find coverage for each feature.
* Shopware assets under `Resources/` are kept separate from runtime code.

---

# Payment Flow

```text
Customer selects Paystack
        ↓
Redirect to Paystack Checkout
        ↓
Customer completes payment
        ↓
Redirect back to Shopware
        ↓
Plugin verifies transaction
        ↓
Order marked as Paid
```

---

# Development & Testing

To ensure a consistent environment, tests and development tools should be run inside the plugin's Docker container.

### 1. Start the Container

```bash
make up
```

### 2. Enter the Container Shell

```bash
make shell
```

### 3. Run Development Commands

Once inside the container, you can execute the following commands:

#### Run Tests
```bash
make test
```

#### Test Coverage
```bash
make test-coverage
```

#### Static Analysis (PHPStan)
```bash
make analyse
```

#### Code Style (PHP-CS-Fixer)
```bash
make cs
make cs-fix
```

---

# Compatibility

| Plugin Version | Shopware Version |
| -------------- | ---------------- |
| ^1.0           | 6.6              |
| ^2.0           | 6.7              |

---

# Troubleshooting

### Payment not updating?

* Ensure webhook/verification flow is working
* Check logs in `var/log/`
* Confirm correct API keys (test vs live)

---

### Plugin not visible?

```bash
bin/console plugin:refresh
```

---

### Cache issues?

```bash
bin/console cache:clear
```

---

# Support

For support:

* Email: [admin@kommandhub.com](mailto:admin@kommandhub.com)
* Website: [https://kommandhub.com](https://kommandhub.com)

---

# License

This project is licensed under the **MIT License**.
See the [LICENSE](LICENSE) file for details.
