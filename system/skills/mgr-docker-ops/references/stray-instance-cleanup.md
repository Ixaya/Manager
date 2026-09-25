# Removing an instance the wrapper refuses to touch

`docker_manage.sh` validates the instance's env/secrets files on every
subcommand, `down` included. An instance that was never validly configured —
typically a stray `sample` run — therefore has no sanctioned way to be torn
down, and the wrapper's own checks block the one command needed to clean it
up. First confirm which instance owns the containers:

```bash
docker ps -a   # containers are named <instance>-*, e.g. local-php-1
```

Then remove the containers and volumes directly:

```bash
docker rm -f <instance>-*                              # from `docker ps -a`
docker volume rm <instance>_*                          # from `docker volume ls`
```

This is the one case where bypassing `docker_manage.sh` is correct. Never
bypass it for routine operations — bring up a real instance (`local`, or
whichever this project uses) through the wrapper afterwards.
