set -euo pipefail
cd ~/customgpt.brianrosenthal.org/
git fetch --prune
git reset --hard origin/dev

# at some point, make a dev site
#set -euo pipefail
#cd ~/customgpt.brianrosenthal.org/
#git fetch --prune
#git reset --hard origin/master
## Optional build steps here (composer install, cache clear, etc.)

