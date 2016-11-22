UPDATE tar_file set backup_created = concat(
    substr(name,-18,4), '-',
    substr(name,-14,2), '-',
    substr(name,-12,2), ' ',
    substr(name,-10,2), ':',
    substr(name,-8,2),  ':',
    substr(name,-6,2)
)
WHERE backup_created IS NULL;

UPDATE tar_file set remote_dir=substr(name,-18,8)
WHERE remote_dir IS NULL