
const { app, BrowserWindow } = require('electron');

function createWindow() {
    const win = new BrowserWindow({
        width: 1200,
        height: 800,
        title: 'BookBridge',
        webPreferences: {
            devTools: false
        }
    });

    win.loadURL('https://librarymain.gamer.gd/');
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});
