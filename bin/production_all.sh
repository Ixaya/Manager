rsync -avhi --no-owner --no-group --delete $1 --exclude 'logs' /home/_user_/app/application/ _user_@vps_x_.ixaya.net:/home/_user_/app/application/
rsync -avhi --no-owner --no-group --delete $1 /home/_user_/app/public/assets/ _user_@vps_x_.ixaya.net:/home/_user_/app/public/assets/
rsync -avhi --no-owner --no-group --delete $1 /home/_user_/app/vendor/ _user_@vps_x_.ixaya.net:/home/_user_/app/vendor/