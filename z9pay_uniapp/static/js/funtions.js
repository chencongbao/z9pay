import secret from "./secret"



export const getLogin = () => {
	try {
		if (uni.getStorageSync('m_mtoken')) {
			return secret.unlock(uni.getStorageSync('m_mtoken')) == uni.getStorageSync('m_token');
		}
		return false;
	} catch (err) {
		return false;
	}

}

export const getName = () => {
	if (uni.getStorageSync('name')) {
		return secret.unlock(uni.getStorageSync('name'));
	}
	return
}

export const getActionLimitCard = () => {
	if (uni.getStorageSync('action_limit_card')) {
		return secret.unlock(uni.getStorageSync('action_limit_card'));
	}
	return
}

export const getUserid = () => {
	if (uni.getStorageSync('userid')) {
		return secret.unlock(uni.getStorageSync('userid'));
	}
	return
}

export const getStatus = () => {
	if (uni.getStorageSync('status')) {
		return secret.unlock(uni.getStorageSync('status'));
	}
	return
}

export const getAgent = () => {
	if (uni.getStorageSync('agent')) {
		return secret.unlock(uni.getStorageSync('agent'));
	}
	return
}

export const getSelfAddBank = () => {
	if (uni.getStorageSync('self_add_bank')) {
		return secret.unlock(uni.getStorageSync('self_add_bank'));
	}
	return
}

export const getActionDelete = () => {
	if (uni.getStorageSync('action_delete')) {
		return secret.unlock(uni.getStorageSync('action_delete'));
	}
	return 1;
}

export const getUsername = () => {
	if (uni.getStorageSync('username')) {
		return secret.unlock(uni.getStorageSync('username'));
	}
	return
}

export const getDepositNotice = () => {
	if (uni.getStorageSync('deposit_notice')) {
		return secret.unlock(uni.getStorageSync('deposit_notice'));
	}
	return
}

export const getTransferNotice = () => {
	if (uni.getStorageSync('transfer_notice')) {
		return secret.unlock(uni.getStorageSync('transfer_notice'));
	}
	return
}


export const getAutoRefresh = () => {
	if (uni.getStorageSync('auto_refresh')) {
		return secret.unlock(uni.getStorageSync('auto_refresh'));
	}
	return
}

export const defaultVoice = () => {
	if (uni.getStorageSync('default_voice')) {
		return uni.getStorageSync('default_voice');
	}
	return
}

export const logout = () => {
	uni.removeStorage({
		key: "m_token"
	});
	uni.removeStorage({
		key: "m_mtoken"
	});
}