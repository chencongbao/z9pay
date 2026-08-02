import secret from "@/static/js/secret.js"

let mutations = {
	setLogin: (state, login) => {
		state.login = login;
	},
	setName: (state, name) => {
		state.name = name;
	},
	setUsername: (state, username) => {
		state.username = username;
	},
	setUserid: (state, userid) => {
		state.userid = userid;
	},
	setStatus: (state, status) => {
		state.status = status;
	},
	setActionDelete: (state, value) => {
		state.action_delete = value;
	},
	setAgent: (state, agent) => {
		state.agent = agent;
	},
	setSelfAddBank: (state, self_add_bank) => {
		state.self_add_bank = self_add_bank;
	},
	setDepositNotice: (state, deposit_notice) => {
		state.deposit_notice = deposit_notice;
	},
	setTransferNotice: (state, transfer_notice) => {
		state.transfer_notice = transfer_notice;
	},
	setAutoRefresh: (state, auto_refresh) => {
		state.auto_refresh = auto_refresh;
	},
	setDefaultVoice: (state, default_voice) => {
		state.default_voice = default_voice;
	},
	setActionLimitCard: (state, action_limit_card) => {
		state.action_limit_card = action_limit_card;
	},
	watchTransferNotice: (state, transfer_notice) => {
		if (transfer_notice == 1) {
			window.Echo.connector.pusher.connection.bind('connected', () => {
				console.log('connected');
			});
			window.Echo.connector.pusher.connection.bind('disconnected', () => {
				console.log('disconnected');
			});
			window.Echo.channel('transfer').listen('.notice', function(data) {
				console.log(data, 'data');
				if (data.user_id == 0) {
					uni.$emit('transferOrder', { data: data });
					var bgAudio = new audioController();
					bgAudio.play(data.voice_url);
				}
				if (data.user_id == state.userid) {
					uni.$emit('transferOrder', { data: data });
					var bgAudio = new audioController();
					bgAudio.play(data.voice_url);
				}
			});
		}
	},
	watchDepositNotice: (state, deposit_notice) => {
		if (deposit_notice == 1) {
			window.Echo.connector.pusher.connection.bind('connected', () => {
				console.log('connected');
			});
			window.Echo.connector.pusher.connection.bind('disconnected', () => {
				console.log('disconnected');
			});
			window.Echo.channel('deposit').listen('.notice', function(data) {
				console.log(data, 'data');
				if (data.user_id == state.userid) {
					var bgAudio = new audioController();
					bgAudio.play(data.voice_url);
					uni.$emit('depositOrder', { data: data });
				}
			});
		}
	},
	leaveTransferNotice: (state) => {
		window.Echo.leaveChannel('transfer');
	},
	leaveDepositNotice: (state) => {
		window.Echo.leaveChannel('deposit');
	}
};
export default mutations;