# FileShare - File Sharing System

FileShare is a web application that enables file sharing between an administrator and guest users. The administrator can manage private files and choose which ones to share with guests.

## 🚀 Requirements

- PHP 7.4 or higher
- Modern web browser
- Local network connection


## 🔧 Installation

1. Clone or download the project into a folder
2. Make sure PHP is installed on your system
3. The `private_files` and `shared_files` folders will be created automatically

## 🚀 Getting Started

### Method 1: PHP Built-in Server (Recommended)
1. Open PowerShell
2. Navigate to the project folder:
   ```powershell
   cd path/to/your/folder/server
   ```
3. Start the server:
   ```powershell
   php -S 0.0.0.0:8080
   ```
4. The server is now accessible:
   - On administrator PC: `http://localhost:8080`
   - On other network devices: `http://PC_IP:8080`
     (Replace PC_IP with the administrator's computer IP address)

### Method 2: Using WAMP
1. Install WAMP
2. Place the project in `C:\wamp64\www\server`
3. Start WAMP
4. Access:
   - On administrator PC: `http://localhost/server`
   - On other devices: `http://PC_IP/server`

## 👥 User Accounts

### Administrator
- Username: admin
- Password: password
- Rights: 
  - File upload
  - Private files management
  - File sharing/unsharing
  - File deletion

### Guest
- Username: guest
- Password: guest123
- Rights:
  - View shared files
  - Download shared files
  - Stream media files

## 🔐 Security

- Change default passwords in `config/app.php`
- Private files are only accessible to administrator
- File extension verification
- Directory traversal protection

## 📱 Mobile Access

1. Connect your mobile device to the same WiFi network as the administrator computer
2. Find the administrator computer's IP address:
   ```powershell
   ipconfig
   ```
   Look for the IPv4 address (e.g., 192.168.x.x)
3. On your mobile, access:
   `http://PC_IP:8080`

## 🛠️ Features

- Responsive interface (mobile-friendly)
- Drag and drop upload
- File preview
- Media streaming
- File search
- Share management
- File deletion

## 📁 Supported File Types

- Images: jpg, jpeg, png
- Documents: pdf, txt
- Media: mp3, mp4

## ⚠️ Troubleshooting

1. **"Forbidden" Error**
   - Check folder permissions
   - Ensure PHP has access rights

2. **Files Not Accessible**
   - Verify you're on the same network
   - Check Windows firewall

3. **Cannot Connect from Mobile**
   - Verify IP address
   - Make sure you're using the correct port

## 📝 Notes

- Keep the PowerShell window open when using the PHP built-in server
- To stop the server: `Ctrl+C` in PowerShell
- File changes are immediate

## 💻 Development

To modify the application:
1. CSS styles are in `assets/css/style.css`
2. JavaScript functions are in `src/js/app.js`
3. Main PHP logic is in `index.php`
4. Configuration settings in `config/app.php`

## 🔒 File Management

### For Administrators
1. Upload files through the web interface
2. Files are initially private
3. Use the share button to make files accessible to guests
4. Use the unshare button to make files private again
5. Delete files using the delete button

### For Guests
1. Browse shared files
2. Use the search function to find specific files
3. Stream media directly in the browser
4. Download files for offline access

## 🌐 Network Configuration

1. **Find Your IP Address**
   ```powershell
   ipconfig
   ```
   Look for "IPv4 Address" under your network adapter

2. **Port Configuration**
   - Default port: 8080
   - Can be changed in the server command:
     ```powershell
     php -S 0.0.0.0:YOUR_PORT
     ```

3. **Firewall Settings**
   - Allow PHP in Windows Firewall
   - Allow the port you're using (8080 by default)

## 🔄 Updates and Maintenance

1. **Updating the System**
   - Regularly check for PHP updates
   - Update your web browser
   - Keep WAMP updated if using it

2. **Maintenance**
   - Regularly clean up unused files
   - Check disk space in shared folders
   - Monitor error logs

## 📚 Best Practices

1. **Security**
   - Change default passwords immediately
   - Regularly update passwords
   - Monitor shared files

2. **Performance**
   - Don't upload extremely large files
   - Clean up old files regularly
   - Optimize images before upload

3. **Usage**
   - Use descriptive file names
   - Organize files logically
   - Regular backups recommended



