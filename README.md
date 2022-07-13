# nl-rdo-zammad-api-export

Usage:

     php zamex.php export <path> [--percentage|-p <percentage>] [--group|-g <group>] [--exclude-group|-x <group>] [--verbose|-v]

where `group` is the zammad group to export, or leave empty when all groups need to be exported.
You can use multiple groups. `exclude-group` allows you to exclude certain groups (only makes sense 
without a `--group` option). It is allowed to have multiple as well.

`path` the actual path to store the ticket exports, and `--percentage` an optional value of how 
many tickets to export. Note that ticket exports are deterministic (always the same tickets will 
be exported), and defaults to 100%. 

Verbose option displays more info.
