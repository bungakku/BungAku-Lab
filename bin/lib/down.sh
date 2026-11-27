lab_down() { . "$LAB_ROOT/bin/lib/svc.sh"; svc_web_stop; svc_db_stop; }
