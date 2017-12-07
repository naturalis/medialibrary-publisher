producer =
owner =

harvestDirectory =
duplicatesDirectory =
resubmitDirectory =
stagingDirectory =
masterDirectory =
wwwDirectory =
logDirectory =
deadImagesDirectory =

numBackupGroups = 0

fileTypes = "jpg,tif,tiff"

logging.stdout = "true"
logging.level = "DEBUG"

db0.host = "localhost"
db0.user = "root"
db0.password = ""
db0.dbname = "nbc_media_library"


offload.immediate = "false"
offload.method = "PHP"
offload.command = tar chPf - %local_dir% | ncftpput -c -u naturalis -p user@pass URL %remote_dir%/%name%
offload.tar.maxSize = 100
offload.tar.maxFiles = 0
offload.ftp.host = ""
offload.ftp.user = ""
offload.ftp.password = ""
offload.ftp.passive = "false"
offload.ftp.initDir = "ftp-test2"
offload.ftp.reconnectPerFile = "false"
offload.ftp.maxConnectionAttempts = 1
offload.ftp.maxUploadAttempts = 1

offload.aws.region = ""
offload.aws.version = "latest"
offload.aws.bucket = ""
offload.aws.key = ""
offload.aws.secret = ""

resizeWhen.fileType = "tiff,jpg,tif,jpeg,gif,png"
resizeWhen.imageSize = 3000
imagemagick.convertCommand = "convert \"%s\" \"%s\""
imagemagick.resizeCommand = "convert \"%s\" -resize 3000x3000^> -quality 80 \"%s\""
imagemagick.maxErrors = 3


imagemagick.command = "convert"
imagemagick.large.size = 1920
imagemagick.large.quality = 100
imagemagick.medium.size = 500
imagemagick.medium.quality = 100
imagemagick.small.size = 100
imagemagick.small.quality = 100
imagemagick.maxErrors = 1

cleaner.minDaysOld = 0
cleaner.sweep = "true"
cleaner.unixRemove = "false"

mail.to = ""
mail.onsuccess = "true"

debug.maxFiles = 0
