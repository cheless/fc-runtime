#!/bin/bash
export PYTHONIOENCODING=utf-8:surrogateescape
ln -snf /var/fc/lang/python3/bin/python3 /usr/local/bin/python
exec /var/fc/lang/python3/bin/python3  -W ignore /var/fc/runtime/python3/bootstrap.py
