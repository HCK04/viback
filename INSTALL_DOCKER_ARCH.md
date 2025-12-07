# Docker Installation Guide for Arch Linux

## 🐳 Install Docker on Arch Linux

### Quick Installation

```bash
# Install Docker
sudo pacman -S docker docker-compose

# Start Docker service
sudo systemctl start docker

# Enable Docker to start on boot
sudo systemctl enable docker

# Add your user to docker group (to run without sudo)
sudo usermod -aG docker $USER

# Apply group changes (logout/login or use newgrp)
newgrp docker

# Verify installation
docker --version
docker-compose --version
```

---

## 📋 Step-by-Step Installation

### 1. Install Docker Package

```bash
sudo pacman -S docker
```

### 2. Install Docker Compose

```bash
sudo pacman -S docker-compose
```

### 3. Start Docker Service

```bash
# Start Docker daemon
sudo systemctl start docker

# Check status
sudo systemctl status docker
```

### 4. Enable Docker on Boot

```bash
sudo systemctl enable docker
```

### 5. Add User to Docker Group

```bash
# Add current user to docker group
sudo usermod -aG docker $USER

# Verify group membership
groups $USER

# Apply changes without logout
newgrp docker
```

**Note:** You may need to logout and login again for group changes to take full effect.

---

## ✅ Verify Installation

```bash
# Check Docker version
docker --version

# Check Docker Compose version
docker-compose --version

# Test Docker (without sudo)
docker run hello-world

# Check Docker service status
sudo systemctl status docker
```

Expected output:
```
Docker version 24.x.x, build xxxxx
Docker Compose version v2.x.x
```

---

## 🔧 Troubleshooting

### Permission Denied Error

If you get "permission denied" when running docker commands:

```bash
# Make sure you're in docker group
groups

# If docker is not listed, add yourself
sudo usermod -aG docker $USER

# Logout and login, or use:
newgrp docker

# Test again
docker ps
```

### Docker Service Not Running

```bash
# Start Docker service
sudo systemctl start docker

# Check for errors
sudo journalctl -u docker.service -n 50
```

### Docker Compose Not Found

```bash
# Install docker-compose
sudo pacman -S docker-compose

# Or install from AUR (docker-compose-v2)
yay -S docker-compose-v2
```

---

## 🚀 After Installation

Once Docker is installed, you can start the Vi-Santé backend:

```bash
cd /home/super_user_zakaria/Dev/vi/viback
./setup.sh
```

---

## 📦 Alternative: Using AUR

If you prefer the latest version from AUR:

```bash
# Install yay (AUR helper) if not installed
sudo pacman -S --needed git base-devel
git clone https://aur.archlinux.org/yay.git
cd yay
makepkg -si

# Install Docker from AUR
yay -S docker-git docker-compose-v2
```

---

## 🔐 Security Notes

### Running Docker Without Sudo

Adding user to docker group allows running Docker without sudo, but note:
- ⚠️ Docker group members have root-equivalent privileges
- ⚠️ Only add trusted users to docker group
- ✅ For development, this is standard practice

### Production Considerations

For production servers:
- Use rootless Docker mode
- Implement proper access controls
- Use Docker secrets for sensitive data
- Enable Docker Content Trust

---

## 📊 Docker Storage

Docker stores data in `/var/lib/docker/` by default.

Check disk usage:
```bash
# Check Docker disk usage
docker system df

# Clean up unused data
docker system prune -a
```

---

## 🔄 Updating Docker

```bash
# Update Docker
sudo pacman -Syu docker docker-compose

# Restart Docker service
sudo systemctl restart docker
```

---

## 📖 Additional Resources

- **Arch Wiki:** https://wiki.archlinux.org/title/Docker
- **Docker Docs:** https://docs.docker.com/engine/install/
- **Docker Compose:** https://docs.docker.com/compose/

---

## ✅ Quick Checklist

- [ ] Install Docker: `sudo pacman -S docker`
- [ ] Install Docker Compose: `sudo pacman -S docker-compose`
- [ ] Start service: `sudo systemctl start docker`
- [ ] Enable on boot: `sudo systemctl enable docker`
- [ ] Add user to group: `sudo usermod -aG docker $USER`
- [ ] Apply changes: `newgrp docker` or logout/login
- [ ] Test: `docker run hello-world`
- [ ] Run setup: `cd /home/super_user_zakaria/Dev/vi/viback && ./setup.sh`

---

**Ready to install!** Run the commands above to get Docker running on Arch Linux.
