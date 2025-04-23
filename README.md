# Parking Lot Management System (PLMS)

This project is a web-based Parking Lot Management System (PLMS) built using PHP, MySQL, TailwindCSS, and Docker.
The Parking Lot Management system is designed to provide a seamless and efficient way to manage parking lots and find parking spots at the University of Calgary.

# Quick Start Guide

# 1. Getting Started

First you need to download [Docker](https://www.docker.com/products/docker-desktop/).
Then scroll down to the "Download Docker Desktop" button and select the correct download based on your machine.

# IMPORTANT NOTE:
- For Windows users:
- After installing [Docker](https://www.docker.com/products/docker-desktop/), you may need to enable virtualization in your system BIOS for Docker Desktop to run properly.  
- On some machines, this is labeled as **SVM** (for AMD CPUs) or **Intel VT-x** (for Intel CPUs). 
- Please refer to your motherboard/computer manufacturer’s documentation for details on how to access BIOS and enable virtualization.

# 2. Build and Run Docker Containers
Open your **terminal** — we recommended to do one of the following:

- VS Code terminal (Recommended)
- Command Prompt (Windows)
- PowerShell (Windows)
- Terminal (Mac/Linux)

- Avoid using **Git Bash** — it may cause issues with Docker volumes or path resolution.

Once your terminal is open, **navigate to the root of the project directory**, and run the following commands:

```bash
docker-compose down -v
docker-compose up --build
```

This will:
- Create and start 3 containers:
  - `db`: MySQL database server
  - `web`: Apache server running PHP on port **8800**
  - `node`: Tailwind CSS watcher for styles

# 4. Access the Application
Once containers are running:
1. Open Docker Desktop
2. Navigate to the `plms` project in Containers
3. Under the **web** service, click the `http://localhost:8800` link

- **Note:** Sometimes the database container (`db`) might not start properly.  
- If you don’t see your tables or encounter connection issues:
  - Open **Docker Desktop**
  - Navigate to the **`plms`** container group
  - Click on the **`db`** service and manually **start** it

# IMPORTANT NOTE:
- Once you are on (http://localhost:8800/) you will see the Homepage. 
- First thing before doing anything, you need to create the tables for the parking slots and that is in `db-test.php`, so go into the **URL bar** at the top and paste this: 
  - http://localhost:8800/db-test.php 
  - Now once you are in this you will see information about our database.
  - Scroll down and find a hyperlink labeled **Initiazlie Database**
  - Click **Initiazlie Database** it will redirect you to this http://localhost:8800/init-db.php 

- Once that has been done you will see that there are 5 parking lots that have been initializated and a total of 250 parking slots. 
- This means that each of the lots have 50 parking slots!
- Now scroll down and find a hyperlink labeled **Go to Homepage** 
- Click **Go to Homepage**

- You should know see the availability of each parking lot on the Homepage!

---

# IMPORTANT NOTE:
- Before creating any users, you need to create the admin accounts and that is in `create-admin.php`, so go into the **URL bar** at the top and paste this:
  - http://localhost:8800/create-admin.php
  - Now once in this you will see that two admin accounts have been created automatically:
    - The **Super Admin**: `admin@ucalgary.ca`
    - The **Regular Admin**: `lotadmin@ucalgary.ca`

- Find a hyperlink labeled **Go to Login Page**
- Click **Go to Login Page**

- You should now be on the login page where you can log in using the admin credentials or start registering new users!


# 4. Admin Access

As an admin who manages parking slots, admins can login to the website with a special set of credentials:

```
Email:    admin@ucalgary.ca
Password: admin123
```

Once logged in, the admin will have access to:

- A comprehensive dashboard showing analytics like total revenue and real-time slot availability
- Tools to view and manage parking lots and slot statuses (e.g., Available, Occupied, Reserved)
- The ability to add new slots, assign types (Compact, Large, Handicapped), and set hourly rates
- Reports summarizing revenue, booking history, and overstay fines
- Event reservation features for managing temporary lot bookings
- Dynamic pricing logic and slot usage tracking
- Manage overstay fees, and ensure that users do not violate parking violations

- If you're unable to log in as admin, it’s likely the `init.sql` script didn’t run correctly due to a database setup issue.
- Refer to the [Build and Run Docker Containers](#2-build-and-run-docker-containers) section above to rebuild the containers properly.


# 5. User Access

Once the database is initialized successfully, you should now be able to:
- Access the Parking Lot Management web application
- View available slots
- Create a new user account
- Once Logged in, users can use additional features such as:
  - Booking parking slots
  - View bookings
  - Manage their bookings: users can cancel or extend a booking if they wish
  - View Payments
  - Edit Profile

# 6. MySQL Access

To connect directly to the MySQL database, run the following command in your terminal:

```bash
docker exec -it db mysql -u root -p
```
When prompted for a password, enter the root password found in your `.env` file:

MYSQL_ROOT_PASSWORD=secure_root_password

This will give you access to the MySQL CLI inside the running Docker container so you can inspect tables, run queries, and check the database state.
