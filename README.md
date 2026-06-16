# Local Web Hosting Services

مجموعه سرویس‌های مختلف که هر کدام در یک Repository جداگانه قرار دارند.

## 📦 Services

| Service | Repository | Description |
|---------|-----------|-------------|
| **Traider** | `/home/hassan/projects/personal/traider-repo` | Grid Trading Bot for Toobit Exchange |
| **Dockers** | `/home/hassan/projects/personal/dockers-repo` | Docker Compose Configuration |
| **FastAPI** | `/home/hassan/projects/personal/fastapi-repo` | LearnArm FastAPI Application |
| **WordPress** | `/home/hassan/projects/personal/wordpress-repo` | WordPress Installations (Ayimi, PhysicalMe) |
| **MariaDB** | `/home/hassan/projects/personal/mariadb-repo` | Database Initialization Scripts |

---

## 🚀 Getting Started

Each service has its own Git repository with full commit history preserved.

```bash
# Clone each service independently
cd /home/hassan/projects/personal

# Service 1: Trading Bot
cd traider-repo
git log
python run_backtest.py  # See CLAUDE.md for commands

# Service 2: Docker Configuration
cd ../dockers-repo
docker compose up -d

# Service 3: FastAPI
cd ../fastapi-repo
# See service README

# Service 4: WordPress
cd ../wordpress-repo
# See service README

# Service 5: Database
cd ../mariadb-repo
# See service README
```

---

## 📋 Important

This directory (`localWebHosting`) is now just a coordination point. All actual development happens in the individual service repositories.

- Do **NOT** commit service code here
- Each service is autonomous
- Update each repository independently

---

## 🔗 Relations

Services can depend on each other:
- **Traider** might use **MariaDB** for historical data
- **Docker** orchestrates all services
- **WordPress** serves content
- **FastAPI** provides APIs

Coordinate changes across repositories as needed.

---

## 📝 Last Updated

Services separated into independent repositories on 2026-06-16.
