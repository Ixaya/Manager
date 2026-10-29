#!/bin/sh
# Refuses to start nginx on a missing or stale runtime render; the render
# itself is nginx-init's, never this container's.
exec /usr/local/bin/nginx-runtime.sh check
