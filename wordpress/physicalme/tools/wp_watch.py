#!/usr/bin/env python3
"""Watch physics-content/articles/ for .md changes and auto-import.

Usage:
    wp_watch.py                      # watch default dir
    wp_watch.py /custom/articles/    # watch a custom directory
"""
import subprocess
import sys
import time
from pathlib import Path
from watchdog.events import FileSystemEventHandler
from watchdog.observers import Observer

ROOT       = Path(__file__).resolve().parent.parent
TOOLS_DIR  = Path(__file__).resolve().parent
DEFAULT_DIR = ROOT / "wp-content/physics-content/articles"
IMPORTER    = TOOLS_DIR / "wp_import_md.py"
PY          = "/tmp/cf_selenium/bin/python"

DEBOUNCE_SECONDS = 1.5  # editors often write multiple times in quick succession


class MdImportHandler(FileSystemEventHandler):
    def __init__(self):
        self._last_run: dict[str, float] = {}

    def _should_run(self, path: str) -> bool:
        if not path.endswith(".md"):
            return False
        # skip dotfiles, swap files, partial writes
        name = Path(path).name
        if name.startswith(".") or name.endswith("~") or name.endswith(".swp"):
            return False
        # debounce
        now = time.time()
        if now - self._last_run.get(path, 0) < DEBOUNCE_SECONDS:
            return False
        self._last_run[path] = now
        return True

    def _run_import(self, path: str):
        print(f"\n[watch] {time.strftime('%H:%M:%S')}  detected change → {Path(path).name}", flush=True)
        try:
            subprocess.run([PY, str(IMPORTER), path], check=True)
        except subprocess.CalledProcessError as e:
            print(f"[watch] import failed (exit {e.returncode})", flush=True)

    def on_modified(self, event):
        if not event.is_directory and self._should_run(event.src_path):
            self._run_import(event.src_path)

    def on_created(self, event):
        if not event.is_directory and self._should_run(event.src_path):
            self._run_import(event.src_path)

    def on_moved(self, event):
        # file renamed/moved-in
        dest = getattr(event, "dest_path", "")
        if dest and self._should_run(dest):
            self._run_import(dest)


def main():
    watch_dir = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_DIR
    if not watch_dir.exists():
        sys.exit(f"directory not found: {watch_dir}")

    handler  = MdImportHandler()
    observer = Observer()
    observer.schedule(handler, str(watch_dir), recursive=False)
    observer.start()

    print(f"[watch] watching {watch_dir}")
    print(f"[watch] importer: {IMPORTER}")
    print(f"[watch] (Ctrl-C to stop)")

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        observer.stop()
        print("\n[watch] stopped")
    observer.join()


if __name__ == "__main__":
    main()
