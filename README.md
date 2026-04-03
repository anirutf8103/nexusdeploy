# 🚀 NEXUSDEPLOY

**A blazing-fast, concurrent FTP deployment dashboard built for PHP developers.**

NEXUSDEPLOY is a robust local deployment engine designed to replace traditional FTP clients like FileZilla. It solves the deployment bottleneck by utilizing concurrent FTP connections and smart incremental file synchronization, turning a 5-minute upload process into a sub-second task.

<img width="1283" height="1041" alt="ภาพถ่ายหน้าจอ 2569-02-26 เวลา 20 35 41" src="https://github.com/user-attachments/assets/c75c4d67-304a-404c-8907-62ca411960a9" />

## 🔥 Why NEXUSDEPLOY?
Uploading hundreds of files via standard FTP clients is slow due to synchronous, one-by-one file transfers and redundant directory checks. NEXUSDEPLOY tackles this by acting as a local command center: it scans your project, identifies only the modified files, and shoots them to your servers simultaneously using `curl_multi_init`.

## ✨ Key Features

* **⚡ Blazing Fast Concurrency:** Opens multiple FTP streams (Batch Processing) simultaneously via PHP cURL, bypassing the limits of traditional synchronous FTP.
* **🧠 Smart Incremental Deploy:** Compares `filemtime()` locally. Only files that are new or have been modified since the last deployment are uploaded.
* **📂 Group Deployment:** Deploy a single codebase to multiple remote servers (e.g., Staging and Production) at the exact same time with one click.
* **🔗 Post-Deploy Webhooks:** Automate your CI/CD pipeline. Trigger database migrations, seeders, or cache-clearing APIs automatically immediately after a successful FTP transfer.
* **📊 Zero-Database Architecture:** Lightweight and portable. All configurations, credentials, and logs are stored in local `.json` files. No MySQL or SQLite required.
* **🌙 Developer-Centric UI:** A sleek, dark-mode dashboard built with Tailwind CSS, featuring real-time terminal logs and Chart.js analytics.

## 🛠️ Tech Stack

* **Backend Engine:** Pure PHP 
* **Frontend:** HTML5, Vanilla JavaScript, Tailwind CSS
* **Data Storage:** JSON File System
* **Core Protocols:** FTP over cURL (`curl_multi_init`), HTTP Webhooks

## 🚀 Getting Started (Local Environment Only)

Because NEXUSDEPLOY needs absolute access to read your local project directories, it **MUST** be run on a local server environment (e.g., XAMPP, Laragon, MAMP, or Docker).

### Installation
1. Clone this repository into your local web server's root directory:
   ```bash
   git clone [https://github.com/your-username/nexusdeploy.git](https://github.com/your-username/nexusdeploy.git)

2. Navigate to the project folder in your browser (e.g., http://localhost/nexusdeploy).

3. The system will automatically generate the required .json storage files in the /data directory on the first run.

4. Go to Servers to add your FTP credentials, then go to Projects to map your local paths to the remote servers.

5. Click Deploy Now and experience the speed!

⚠️ Security & Privacy Warning
This application is strictly designed for LOCAL USE ONLY.
NEVER upload or host NEXUSDEPLOY on a public-facing web server. It contains full read access to your local file system and stores sensitive FTP passwords in plain text JSON files. The /data directory is included in .gitignore by default to prevent accidental credential leaks.

Crafted with 💻 and ☕ to end deployment headaches.
