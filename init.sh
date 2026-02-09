#!/bin/bash

# RapidHTML2PNG - Development Environment Setup Script
# This script sets up the PHP 7.4 Docker environment for development

set -e  # Exit on error

echo "=========================================="
echo "RapidHTML2PNG - Development Setup"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if docker-compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "Error: docker-compose is not installed. Please install docker-compose first."
    exit 1
fi

echo -e "${GREEN}✓${NC} Docker and docker-compose found"

# Create necessary directories
echo ""
echo "Creating project directories..."
mkdir -p assets/media/rapidhtml2png
mkdir -p logs

# Set permissions for output directory
chmod 755 assets/media/rapidhtml2png

echo -e "${GREEN}✓${NC} Directories created"

# Build and start Docker container
echo ""
echo "Building Docker container..."
docker-compose build

echo ""
echo "Starting Docker container..."
docker-compose up -d

# Wait for container to be ready
echo ""
echo "Waiting for PHP container to be ready..."
sleep 5

# Check if container is running
if docker-compose ps | grep -q "Up"; then
    echo -e "${GREEN}✓${NC} PHP container is running"
else
    echo "Error: PHP container failed to start"
    exit 1
fi

# Display PHP version
echo ""
echo "PHP version in container:"
docker-compose exec -T app php -v

# Check PHP extensions
echo ""
echo "Checking PHP extensions:"
docker-compose exec -T app php -m | grep -E "(curl|gd|mbstring)" || echo "Warning: Some required extensions may be missing"

# Display access information
echo ""
echo "=========================================="
echo -e "${GREEN}Setup Complete!${NC}"
echo "=========================================="
echo ""
echo "PHP Development Server is running at:"
echo "  http://localhost:8080"
echo ""
echo "API Endpoint:"
echo "  POST http://localhost:8080/convert.php"
echo ""
echo "Test files location:"
echo "  - test_html_to_render.html"
echo "  - main.css"
echo ""
echo "Output directory:"
echo "  - assets/media/rapidhtml2png/"
echo ""
echo "Useful commands:"
echo "  - View logs: docker-compose logs -f"
echo "  - Stop server: docker-compose stop"
echo "  - Restart server: docker-compose restart"
echo "  - SSH into container: docker-compose exec app bash"
echo ""
echo "=========================================="
