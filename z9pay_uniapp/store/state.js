import { getLogin, getName, getUsername, getUserid, getStatus, getAgent, getDepositNotice, getTransferNotice, defaultVoice, getSelfAddBank, getAutoRefresh, getActionDelete, getActionLimitCard } from "../static/js/funtions";
let state = {
	login: getLogin(),
	name: getName(),
	username: getUsername(),
	userid: getUserid(),
	status: getStatus(),
	agent: getAgent(),
	deposit_notice: getDepositNotice(),
	transfer_notice: getTransferNotice(),
	default_voice: defaultVoice(),
	self_add_bank: getSelfAddBank(),
	auto_refresh: getAutoRefresh(),
	action_delete: getActionDelete(),
	action_limit_card: getActionLimitCard()
}
export default state