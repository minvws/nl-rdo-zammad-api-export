# nl-rdo-zammad-api-export

Usage:

     php zamex.php export <group> <path> [--percentage|-p <percentage>]

where `group` is the zammad group to export, `path` the actual path to store the ticket exports, and `--percentage` an 
optional value of how many tickets to export. Note that ticket exports are deterministic (always the same tickets will 
be exported), and defaults to 10%. 
