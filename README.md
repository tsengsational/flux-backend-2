# Headless WordPress Backend

This project sets up a headless WordPress installation containerized using Docker, optimized for use as a backend API.

## Project Structure

```
.
├── docker-compose.yml          # Docker Compose configuration
├── wordpress/                  # WordPress files
│   ├── wp-content/            # WordPress content directory
│   │   ├── plugins/          # Custom plugins
│   │   ├── themes/           # Custom themes
│   │   └── uploads/          # Media uploads
│   └── wp-config.php         # WordPress configuration
└── .github/workflows/         # GitHub Actions deployment
```

## Prerequisites

- Docker and Docker Compose
- SSH access to the Oracle instance (150.136.240.129)

## Setup Instructions

1. Clone the repository
2. Generate secure authentication keys and salts for wp-config.php
3. Run `docker-compose up -d` to start the WordPress backend
4. Access WordPress admin at `http://localhost:8000/wp-admin`
5. Install and configure the following recommended plugins:
   - WP REST API
   - JWT Authentication for WP REST API
   - Advanced Custom Fields (if needed)

## Directory Structure

- `wordpress/wp-content/plugins/`: Place custom plugins here
- `wordpress/wp-content/themes/`: Place custom themes here
- `wordpress/wp-content/uploads/`: Media uploads directory

## Deployment

The project is configured to deploy automatically to the Oracle instance (150.136.240.129) on port 8000 when changes are pushed to the main branch.

### Required GitHub Secrets

- `SSH_PRIVATE_KEY`: Private SSH key for accessing the Oracle instance
- `SSH_KNOWN_HOSTS`: Known hosts entry for the Oracle instance

## Security Notes

1. Change the default WordPress and MySQL passwords in production
2. Generate unique authentication keys and salts in wp-config.php
3. Ensure proper firewall rules are in place
4. Use HTTPS in production
5. Regularly update WordPress core and plugins
6. Implement proper backup strategy

## API Endpoints

The WordPress REST API will be available at:
- Base URL: `http://150.136.240.129:8000/wp-json/wp/v2`
- Authentication: JWT token-based authentication 