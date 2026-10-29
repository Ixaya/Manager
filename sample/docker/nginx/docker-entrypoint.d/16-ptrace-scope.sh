#!/bin/sh
# Master and workers share uid 101, so where the host's Yama ptrace_scope is
# 0 (or Yama is absent) a compromised worker can attach to the master.
# A warning, not a refusal: dev VMs often have no Yama at all.
scope="$(cat /proc/sys/kernel/yama/ptrace_scope 2>/dev/null || echo absent)"
case "$scope" in
    0|absent) echo "[ptrace-scope] WARNING: kernel.yama.ptrace_scope is $scope; set it to 1 or higher on the host" >&2 ;;
esac
exit 0
