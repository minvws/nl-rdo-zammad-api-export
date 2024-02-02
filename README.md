# Zammad Ticket Export

This can be used to export tickets from [Zammad](https://zammad.org) to PDF.
Each ticket will have its own PDF file.

This is not intended as a backup or restore solution only intended for archiving tickets.

You should not host this script at a location that is publicly available.

## Prereqs

- PHP 8.0+ (https://www.php.net/manual/en/install.php)
- Composer (https://getcomposer.org/download/)

## Installation

- Clone or download this project
- Run composer install:
    ```
    composer install --prefer-dist --no-interaction
    ```
  Or for a local install:
    ```
    php composer.phar install --prefer-dist --no-interaction
    ```
- Copy .env.example to .env

    ```
    cp .env.example .env
    ```
- Create personal access token with the following permissions in Zammad
    - admin.group
    - admin.ticket
    - admin.tag
    - ticket.agent or ticket.customer
- Set Zammad url and token in .env

## Usage

Run:

     php zamex.php export <path> [--percentage|-p <percentage>] [--group|-g <group>] [--exclude-group|-x <group>] [--verbose|-v]

where `group` is the zammad group to export, or leave empty when all groups need to be exported.
You can use multiple groups. `--exclude-group` allows you to exclude certain groups (only makes sense
without a `--group` option). It is allowed to have multiple as well.

`path` the actual path to store the ticket exports, and `--percentage` an optional value of how
many tickets to export. Note that ticket exports are deterministic (always the same tickets will
be exported), and defaults to 100%.

Verbose option displays more info.

## Development

To work on this Zammad Export Tool, you'll need a Zammad instance for testing and development. You can connect the export script to any Zammad API of your choice. For local development, it is possible to run Zammad locally using Docker Compose. Full instructions can be found [here](https://docs.zammad.org/en/latest/install/docker-compose.html).

**Note:** This setup is supported on Docker for Windows and Linux (amd64 architecture). At the time of writing, MacOS under Apple Silicon is not officially supported.

**Important:** Zammad in Docker should only be used for development purposes. Avoid using this configuration in production environments.

## Contribution

If you plan to make non-trivial changes, we recommend to open an issue beforehand where we can discuss your planned changes. This increases the chance that we might be able to use your contribution (or it avoids doing work if there are reasons why we wouldn't be able to use it).

Git commits must be signed https://docs.github.com/en/github/authenticating-to-github/signing-commits

## License

This repository is released under the EUPL 1.2 license. [See LICENSE](LICENSE.TXT) for details.
