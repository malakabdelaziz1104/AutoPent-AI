# 🛡️ AutoPent AI

**AI-Powered Vulnerability Scanner & Remediation Tool**

AutoPent AI is an automated penetration testing system that bridges the gap between vulnerability detection and code correction. It integrates industry-standard security tools into a single engine and uses AI to generate specific, actionable code fixes — turning security scanning from a passive report into an active defense mechanism.

---

## Live Preview

![Homepage](screenshots/homepage.png)

---

##  The Problem

Manual penetration testing is time-consuming, resource-intensive, and requires specialized expertise. Existing automated tools are often fragmented and generate passive technical reports that highlight risks without offering clear, actionable solutions — leaving systems vulnerable for longer.

## The Solution

AutoPent AI combines multiple open-source security tools into one platform, then uses an AI Remediation Module to analyze findings and generate syntax-correct code fixes, so developers can secure their applications quickly and efficiently.

---

##  Key Features

- **Automated Web Vulnerability Scanning** using OWASP ZAP / Burp Suite
- **Network Port Analysis** using Nmap
- **Infrastructure Vulnerability Management** using OpenVAS
- **AI-Driven Remediation Module** — generates real code fixes, not just warnings
- **Secure Authentication** (Login / Signup / Email Verification)
- **Detailed PDF Reports** with severity breakdown (Critical, High, Medium, Low, Info)
- **Scan History Log** to track previous scans
- Built following **Agile SDLC** methodology

---

##  System Architecture

[System Architecture](screenshots/architecture.png)

The system follows a layered architecture:
- **Presentation Layer:** React.js Frontend Dashboard
- **Application Logic Layer:** Backend Controller (API Gateway, Auth Manager, Scan Orchestrator)
- **Integration & Execution Layer:** Scanning Engines (Nmap, OWASP ZAP, OpenVAS) + AI Remediation Module
- **Data Layer:** MySQL Database + PDF Report Storage

---

##  Dashboard

[Dashboard](screenshots/dashboard.png)

Users can launch scans by entering a target URL/IP, with configurable options like comprehensive scans (OpenVAS), web vulnerability scans (Burp Suite), and advanced Nmap options.

---

##  Sample Report

[Vulnerability Report](screenshots/report-summary.png)

Each scan generates a detailed report showing vulnerabilities categorized by severity.

### Example Findings:

[SQL Injection Finding](screenshots/vulnerability-detail-1.png)

[Open Port Finding](screenshots/vulnerability-detail-2.png)

Every vulnerability comes with a **clear remediation/solution section**, making it actionable instead of just informational.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | React.js |
| Backend | PHP, Python |
| Scanning Engines | Nmap, OWASP ZAP, Burp Suite, OpenVAS |
| AI Module | LLM-based Remediation Engine |
| Database | MySQL |
| Reports | PDF Generation |

---

## Project Structure
AutoPent-AI/
├── source_code/          # Full application source code
├── screenshots/          # UI & report screenshots
├── Autopent AI System.pdf
├── Reporttt.pdf           # Full graduation project report
├── SWOT Analysis.pdf

---
## Team
This was a graduation project developed by a team of 8 students at Canadian International College (CIC), under the supervision of Dr. Ahmed Gaber Abuabdallah.

---

## Future Work

- Real-time continuous monitoring
- Automated remediation (auto-patching)
- Mobile application penetration testing support
- Cloud deployment for scalability

---

## Disclaimer

This tool is intended for **authorized security testing only**. Always ensure you have explicit permission before scanning any target.

## Demo Video

[▶️ Watch/Download the demo video](video%20demo.mp4)
