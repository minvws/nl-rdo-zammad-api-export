# nl-rdo-zammad-api-export

Usage:

     php zamex.php export <path> [--percentage|-p <percentage>] [--group|-g <group>] [--verbose|-v]

where `group` is the zammad group to export, or leave empty when all groups need to be exported.
`path` the actual path to store the ticket exports, and `--percentage` an optional value of how 
many tickets to export. Note that ticket exports are deterministic (always the same tickets will 
be exported), and defaults to 10%. 

Verbose option displays more info.
