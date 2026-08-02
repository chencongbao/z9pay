import Echo from "laravel-echo"
import  Pusher from "pusher-js"

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: "f366a74454b644d20ddf",
    wsHost: window.location.hostname,
    wsPort: 80,
    wssPort: 443,
    cluster: "ap1",
    forceTLS: false,
    disableStats: true,
    enabledTransports:['ws', 'wss']
});
const conn = window.Echo.connector.pusher.connection;
conn.bind('state_change', (states) => {
    console.log('state_change:', states.previous, '=>', states.current);
});

