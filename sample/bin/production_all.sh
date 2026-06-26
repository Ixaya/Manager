rsync -avhi --no-owner --no-group --delete $1 --exclude 'logs' /home/{user}/app/application/ {user}@{example.com}:/home/_user_/app/application/
# rsync -avhi --no-owner --no-group --delete $1 /home/{user}/app/public/assets/ {user}@{example.com}:/home/_user_/app/public/assets/
# rsync -avhi --no-owner --no-group --delete $1 /home/{user}/app/vendor/ {user}@{example.com}:/home/_user_/app/vendor/