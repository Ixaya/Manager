#!/bin/sh
# Last init step: clears postgres/entrypoint.sh's incomplete-init marker.
# Every other init script must sort before this one, or its failure goes unseen.
rm -f /var/lib/postgresql/.mgr-init/init-incomplete
