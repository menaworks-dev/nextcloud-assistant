<!--
  - SPDX-FileCopyrightText: 2026 MenaWorks
  - SPDX-License-Identifier: AGPL-3.0-or-later
  - Rich rendering for Claude tool results in assistant chat
-->
<template>
	<div v-if="tools.length" class="tool-cards">
		<div class="tool-cards__header" @click="expanded = !expanded">
			<WrenchIcon :size="16" />
			<span>{{ tools.length }} araç kullanıldı</span>
			<ChevronDownIcon v-if="!expanded" :size="16" />
			<ChevronUpIcon v-if="expanded" :size="16" />
		</div>
		<div v-if="expanded" class="tool-cards__list">
			<div v-for="(tool, idx) in tools"
				:key="idx"
				class="tool-card"
				:class="['tool-card--' + getCategory(tool.name), { 'tool-card--error': isErrorResult(tool) }]">
				<div class="tool-card__header">
					<component :is="getCategoryIcon(tool.name)" :size="18" />
					<strong class="tool-card__name">{{ formatName(tool.name) }}</strong>
					<span class="tool-card__category">{{ getCategory(tool.name) }}</span>
					<span v-if="isErrorResult(tool)" class="tool-card__error-badge">hata</span>
				</div>
				<div v-if="Object.keys(tool.args || {}).length && !isErrorResult(tool)" class="tool-card__args">
					<span v-for="(val, key) in tool.args"
						:key="key"
						class="tool-card__arg">
						<span class="tool-card__arg-key">{{ key }}:</span> {{ formatArgValue(val) }}
					</span>
				</div>
				<!-- File list results -->
				<div v-if="isFileResult(tool)" class="tool-card__files">
					<div v-for="(file, fi) in getArrayResult(tool)"
						:key="fi"
						class="file-item"
						@click="openFile(file)">
						<img v-if="file.fileid && isImageMime(file.mime)"
							:src="getThumbnailUrl(file.fileid)"
							class="file-item__thumb"
							loading="lazy">
						<component :is="getFileIcon(file)"
							v-else
							:size="18"
							class="file-item__icon" />
						<span class="file-item__name">{{ file.basename || file.filename || 'Dosya' }}</span>
						<span v-if="file.size > 0" class="file-item__size">{{ formatSize(file.size) }}</span>
					</div>
				</div>
				<!-- Calendar event results -->
				<div v-if="isCalendarResult(tool)" class="tool-card__events">
					<div v-for="(event, ei) in getArrayResult(tool)"
						:key="ei"
						class="event-item"
						:style="event.color ? { borderLeftColor: event.color } : {}">
						<CalendarIcon :size="16" class="event-item__icon" />
						<div class="event-item__details">
							<strong>{{ event.summary || 'Etkinlik' }}</strong>
							<span v-if="event.start">{{ event.start }}{{ event.end ? ' — ' + event.end : '' }}</span>
							<span v-if="event.description" class="event-item__desc">{{ event.description }}</span>
						</div>
					</div>
				</div>
				<!-- Search results -->
				<div v-if="isSearchResult(tool)" class="tool-card__search">
					<div v-for="(item, si) in getArrayResult(tool)"
						:key="si"
						class="search-item"
						@click="openSearchResult(item)">
						<img v-if="item.fileid && isImageTitle(item.title)"
							:src="getThumbnailUrl(item.fileid)"
							class="search-item__thumb"
							loading="lazy">
						<MagnifyIcon v-else :size="16" class="search-item__icon" />
						<div class="search-item__details">
							<strong>{{ item.title || item.name || item.basename || 'Sonuç' }}</strong>
							<span v-if="item.subline || item.path">{{ item.subline || item.path }}</span>
						</div>
					</div>
				</div>
				<!-- Contact results -->
				<div v-if="isContactResult(tool)" class="tool-card__contacts">
					<div v-for="(contact, ci) in getArrayResult(tool)"
						:key="ci"
						class="contact-item">
						<AccountIcon :size="16" class="contact-item__icon" />
						<div class="contact-item__details">
							<strong>{{ contact.fullName || contact.name || 'Kişi' }}</strong>
							<span v-if="contact.email">{{ contact.email }}</span>
							<span v-if="contact.phone">{{ contact.phone }}</span>
						</div>
					</div>
				</div>
				<!-- Talk messages -->
				<div v-if="isTalkResult(tool)" class="tool-card__talk">
					<div v-for="(msg, mi) in getArrayResult(tool)"
						:key="mi"
						class="talk-item"
						:class="{ 'talk-item--system': msg.system }">
						<strong v-if="!msg.system" class="talk-item__actor">{{ msg.actor }}</strong>
						<span class="talk-item__message">{{ msg.message }}</span>
						<span v-if="msg.timestamp" class="talk-item__time">{{ formatTimestamp(msg.timestamp) }}</span>
					</div>
				</div>
				<!-- Mail messages -->
				<div v-if="isMailResult(tool)" class="tool-card__mail">
					<div v-for="(mail, mi) in getArrayResult(tool)"
						:key="mi"
						class="mail-item"
						:class="{ 'mail-item--unread': !mail.seen }">
						<EmailIcon :size="16" class="mail-item__icon" />
						<div class="mail-item__details">
							<div class="mail-item__top">
								<strong>{{ mail.subject || 'Konu yok' }}</strong>
								<span v-if="mail.hasAttachments" class="mail-item__attach">📎</span>
							</div>
							<span class="mail-item__from">{{ mail.from }}</span>
							<span v-if="mail.date" class="mail-item__date">{{ formatTimestamp(mail.date) }}</span>
						</div>
					</div>
				</div>
				<!-- Notes -->
				<div v-if="isNoteResult(tool)" class="tool-card__notes">
					<div v-for="(note, ni) in getArrayResult(tool)"
						:key="ni"
						class="note-item">
						<NoteIcon :size="16" class="note-item__icon" />
						<div class="note-item__details">
							<strong>{{ note.title || 'Not' }}</strong>
							<span v-if="note.preview" class="note-item__preview">{{ note.preview }}</span>
							<span v-if="note.category" class="note-item__category">{{ note.category }}</span>
						</div>
						<span v-if="note.favorite" class="note-item__fav">⭐</span>
					</div>
				</div>
				<!-- Deck cards -->
				<div v-if="isDeckResult(tool)" class="tool-card__deck">
					<div v-for="(card, di) in getArrayResult(tool)"
						:key="di"
						class="deck-item"
						:class="{ 'deck-item--done': card.done }"
						@click="openDeck(card)">
						<div v-if="card.labels && card.labels.length" class="deck-item__labels">
							<span v-for="(label, li) in card.labels"
								:key="li"
								class="deck-item__label"
								:style="{ background: '#' + (label.color || '0082c9') }">
								{{ label.title }}
							</span>
						</div>
						<strong>{{ card.title || 'Kart' }}</strong>
						<span v-if="card.description" class="deck-item__desc">{{ card.description }}</span>
						<span v-if="card.owner" class="deck-item__desc">Sahip: {{ card.owner }}</span>
						<div v-if="card.duedate || (card.assignedUsers && card.assignedUsers.length)" class="deck-item__meta">
							<span v-if="card.duedate">{{ card.duedate }}</span>
							<span v-for="(user, ui) in (card.assignedUsers || [])" :key="ui">{{ user }}</span>
						</div>
					</div>
				</div>
				<!-- Notifications -->
				<div v-if="isNotificationResult(tool)" class="tool-card__notifs">
					<div v-for="(notif, ni) in getArrayResult(tool)"
						:key="ni"
						class="notif-item">
						<BellIcon :size="16" class="notif-item__icon" />
						<div class="notif-item__details">
							<strong>{{ notif.subject || 'Bildirim' }}</strong>
							<span v-if="notif.message">{{ notif.message }}</span>
							<span v-if="notif.datetime" class="notif-item__time">{{ formatDate(notif.datetime) }}</span>
						</div>
						<span class="notif-item__app">{{ notif.app }}</span>
					</div>
				</div>
				<!-- Shares -->
				<div v-if="isShareResult(tool)" class="tool-card__shares">
					<div v-for="(share, si) in getArrayResult(tool)"
						:key="si"
						class="share-item">
						<ShareIcon :size="16" class="share-item__icon" />
						<div class="share-item__details">
							<strong>{{ share.path || 'Paylaşım' }}</strong>
							<span>{{ getShareTypeLabel(share.shareType) }} → {{ share.shareWith || 'Herkese açık' }}</span>
						</div>
						<span class="share-item__perms">{{ getPermLabel(share.permissions) }}</span>
					</div>
				</div>
				<!-- Text result (pre-formatted by MCP) -->
				<div v-if="isGenericResult(tool) && isTextResult(tool)"
					class="tool-card__text">
					<div v-for="(line, li) in formatTextLines(tool.result)"
						:key="li"
						class="text-line"
						:class="{ 'text-line--header': line.startsWith('#') || line.endsWith(':') }">
						{{ line }}
					</div>
				</div>
				<!-- JSON result preview -->
				<div v-if="isGenericResult(tool) && !isTextResult(tool) && tool.result"
					class="tool-card__result">
					<details>
						<summary>Sonuç</summary>
						<pre>{{ formatResult(tool.result) }}</pre>
					</details>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import WrenchIcon from 'vue-material-design-icons/Wrench.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FileIcon from 'vue-material-design-icons/File.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import FileImageIcon from 'vue-material-design-icons/FileImage.vue'
import FileMusicIcon from 'vue-material-design-icons/FileMusic.vue'
import FileVideoIcon from 'vue-material-design-icons/FileVideo.vue'
import FilePdfBoxIcon from 'vue-material-design-icons/FilePdfBox.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import MessageIcon from 'vue-material-design-icons/Message.vue'
import EmailIcon from 'vue-material-design-icons/Email.vue'
import NoteIcon from 'vue-material-design-icons/Note.vue'
import ViewDashboardIcon from 'vue-material-design-icons/ViewDashboard.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import BellIcon from 'vue-material-design-icons/Bell.vue'
import ShareIcon from 'vue-material-design-icons/Share.vue'

import { generateUrl } from '@nextcloud/router'

const TOOL_CATEGORIES = {
	list_files: 'files',
	read_file: 'files',
	write_file: 'files',
	create_folder: 'files',
	delete: 'files',
	move_file: 'files',
	copy_file: 'files',
	get_file_info: 'files',
	search_files: 'files',
	get_file_content: 'files',
	bulk_file_operations: 'files',
	analyze_image: 'files',
	list_calendars: 'calendar',
	get_calendar: 'calendar',
	create_calendar: 'calendar',
	list_events: 'calendar',
	create_event: 'calendar',
	update_event: 'calendar',
	delete_event: 'calendar',
	get_event: 'calendar',
	list_contacts: 'contacts',
	get_contact: 'contacts',
	create_contact: 'contacts',
	update_contact: 'contacts',
	delete_contact: 'contacts',
	search_contacts: 'contacts',
	list_address_books: 'contacts',
	talk_list_conversations: 'talk',
	talk_list_messages: 'talk',
	talk_send_message: 'talk',
	talk_create_conversation: 'talk',
	talk_list_participants: 'talk',
	talk_add_participant: 'talk',
	talk_remove_participant: 'talk',
	talk_delete_message: 'talk',
	talk_create_poll: 'talk',
	talk_react_to_message: 'talk',
	mail_list_messages: 'mail',
	mail_read_message: 'mail',
	mail_send_message: 'mail',
	mail_delete_message: 'mail',
	mail_move_message: 'mail',
	mail_set_message_flags: 'mail',
	list_mail_accounts: 'mail',
	list_mailboxes: 'mail',
	list_notes: 'notes',
	get_note: 'notes',
	create_note: 'notes',
	update_note: 'notes',
	delete_note: 'notes',
	deck_list_boards: 'deck',
	deck_get_board: 'deck',
	deck_create_board: 'deck',
	deck_list_stacks: 'deck',
	deck_create_stack: 'deck',
	deck_get_card: 'deck',
	deck_create_card: 'deck',
	deck_update_card: 'deck',
	deck_move_card: 'deck',
	deck_archive_card: 'deck',
	deck_assign_label: 'deck',
	deck_assign_user: 'deck',
	list_notifications: 'notifications',
	get_notification: 'notifications',
	delete_notification: 'notifications',
	mark_notification_read: 'notifications',
	delete_all_notifications: 'notifications',
	list_shares: 'shares',
	get_share: 'shares',
	create_share: 'shares',
	update_share: 'shares',
	delete_share: 'shares',
	unified_search: 'search',
	list_search_providers: 'search',
}

const CATEGORY_ICONS = {
	files: FolderIcon,
	calendar: CalendarIcon,
	contacts: AccountIcon,
	talk: MessageIcon,
	mail: EmailIcon,
	notes: NoteIcon,
	deck: ViewDashboardIcon,
	search: MagnifyIcon,
	notifications: BellIcon,
	shares: ShareIcon,
}

export default {
	name: 'ToolCards',
	components: {
		WrenchIcon,
		ChevronDownIcon,
		ChevronUpIcon,
		FolderIcon,
		FileIcon,
		FileDocumentIcon,
		FileImageIcon,
		FileMusicIcon,
		FileVideoIcon,
		FilePdfBoxIcon,
		CalendarIcon,
		AccountIcon,
		MessageIcon,
		EmailIcon,
		NoteIcon,
		ViewDashboardIcon,
		MagnifyIcon,
		BellIcon,
		ShareIcon,
	},
	props: {
		tools: { type: Array, default: () => [] },
	},
	data: () => ({ expanded: true }),
	methods: {
		getCategory(name) {
			return TOOL_CATEGORIES[name] || 'other'
		},
		getCategoryIcon(name) {
			return CATEGORY_ICONS[this.getCategory(name)] || WrenchIcon
		},
		formatName(name) {
			return name.replace(/_/g, ' ')
		},
		formatArgValue(val) {
			if (typeof val === 'object') return JSON.stringify(val)
			return String(val)
		},
		formatSize(bytes) {
			if (bytes < 1024) return bytes + ' B'
			if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
			if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
			return (bytes / (1024 * 1024 * 1024)).toFixed(1) + ' GB'
		},
		formatDate(dateStr) {
			try {
				const d = new Date(dateStr)
				return d.toLocaleDateString('tr-TR', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
			} catch { return dateStr }
		},
		formatResult(result) {
			if (typeof result === 'object') return JSON.stringify(result, null, 2).substring(0, 500)
			try {
				const parsed = JSON.parse(result)
				return JSON.stringify(parsed, null, 2).substring(0, 500)
			} catch { return String(result).substring(0, 500) }
		},
		getArrayResult(tool) {
			try {
				const data = typeof tool.result === 'string' ? JSON.parse(tool.result) : tool.result
				if (Array.isArray(data)) return data.slice(0, 50)
				if (data && typeof data === 'object') {
					const arr = data.entries || data.events || data.results || data.contacts || data.items
					if (Array.isArray(arr)) return arr.slice(0, 50)
					return [data]
				}
				return []
			} catch {
				// JSON parse failed — try text parsers
				if (typeof tool.result === 'string') {
					const parsed = this.parseTextResult(tool.name, tool.result)
					if (parsed.length > 0) return parsed
				}
				return []
			}
		},
		parseTextResult(name, text) {
			const cat = TOOL_CATEGORIES[name]
			if (cat === 'notifications') return this.parseNotificationText(text)
			if (cat === 'deck') return this.parseDeckText(text)
			if (cat === 'calendar') return this.parseCalendarText(text)
			if (cat === 'contacts') return this.parseContactText(text)
			return []
		},
		parseNotificationText(text) {
			const items = []
			const lines = text.split('\n')
			let current = null
			for (const line of lines) {
				const m = line.match(/^- \[(.+?)\]\s*(.+?)\s*\((\d{4}-\d{2}-\d{2}T[\d:+]+)\)\s*$/)
				if (m) {
					if (current) items.push(current)
					current = { app: m[1], subject: m[2], datetime: m[3], message: '' }
				} else if (current && line.match(/^\s{2,}/) && line.trim()) {
					current.message += (current.message ? ' ' : '') + line.trim()
				} else if (line.match(/^- \[/)) {
					// Notification line without standard datetime — try broader match
					if (current) items.push(current)
					const m2 = line.match(/^- \[(.+?)\]\s*(.+)$/)
					if (m2) current = { app: m2[1], subject: m2[2], datetime: '', message: '' }
				}
			}
			if (current) items.push(current)
			return items
		},
		parseDeckText(text) {
			const items = []
			const lines = text.split('\n')
			for (const line of lines) {
				const m = line.match(/^\[(\d+)\]\s*(.+?)\s*\(owner:\s*(.+?),\s*(\d+)\s*labels?\)/)
				if (m) {
					items.push({ id: parseInt(m[1]), title: m[2], owner: m[3], labelCount: parseInt(m[4]) })
				}
			}
			return items
		},
		parseCalendarText(text) {
			const items = []
			const blocks = text.split(/\n(?=\S)/)
			for (const block of blocks) {
				const lines = block.split('\n').map(l => l.trim()).filter(Boolean)
				if (!lines.length) continue
				// Skip header lines like "Events in ... (N found):" or "Calendars (N found):"
				if (lines[0].match(/^(Events|Calendars)\s/)) continue
				const summary = lines[0]
				let start = ''
				let end = ''
				const location = ''
				let description = ''
				let url = ''
				for (let i = 1; i < lines.length; i++) {
					const wm = lines[i].match(/^When:\s*(.+?)\s*-\s*(.+)$/)
					if (wm) { start = wm[1]; end = wm[2]; continue }
					const sm = lines[i].match(/^Supports:\s*(.+)$/)
					if (sm) continue
					const um = lines[i].match(/^URL:\s*(.+)$/)
					if (um) { url = um[1]; continue }
					// Everything else is description
					if (!lines[i].match(/^(UID|When|Supports|URL):/)) {
						description += (description ? ' ' : '') + lines[i]
					}
				}
				// Calendar list item format: "Name [#color]"
				const calMatch = summary.match(/^(.+?)\s*\[(#[0-9a-fA-F]+)\]$/)
				if (calMatch) {
					items.push({ summary: calMatch[1], color: calMatch[2], url, start, end })
				} else {
					items.push({ summary, start, end, location, description })
				}
			}
			return items
		},
		parseContactText(text) {
			const items = []
			const blocks = text.split(/\n(?=\S)/)
			for (const block of blocks) {
				const lines = block.split('\n').map(l => l.trim()).filter(Boolean)
				if (!lines.length || lines[0].match(/^(Contacts|Address Books)\s/)) continue
				const name = lines[0]
				let email = ''
				let phone = ''
				let org = ''
				for (let i = 1; i < lines.length; i++) {
					const em = lines[i].match(/(?:Email|E-posta):\s*(.+)/i)
					if (em) { email = em[1]; continue }
					const pm = lines[i].match(/(?:Phone|Tel|Telefon):\s*(.+)/i)
					if (pm) { phone = pm[1]; continue }
					const om = lines[i].match(/(?:Org|Organization|Kurum):\s*(.+)/i)
					if (om) { org = om[1]; continue }
				}
				items.push({ fullName: name, email, phone, org })
			}
			return items
		},
		isFileResult(tool) {
			return ['list_files', 'search_files', 'get_file_info'].includes(tool.name)
				&& tool.result && this.getArrayResult(tool).length > 0
		},
		isCalendarResult(tool) {
			return ['list_events', 'create_event', 'get_event', 'get_calendar', 'list_calendars'].includes(tool.name)
				&& tool.result && this.getArrayResult(tool).length > 0
		},
		isSearchResult(tool) {
			return tool.name === 'unified_search' && tool.result && this.getArrayResult(tool).length > 0
		},
		isContactResult(tool) {
			return ['list_contacts', 'get_contact', 'search_contacts', 'list_address_books'].includes(tool.name)
				&& tool.result && this.getArrayResult(tool).length > 0
		},
		isTalkResult(tool) {
			return ['talk_list_messages', 'talk_list_conversations'].includes(tool.name)
				&& tool.result && this.getArrayResult(tool).length > 0
		},
		isMailResult(tool) {
			return ['mail_list_messages', 'mail_read_message'].includes(tool.name)
				&& tool.result && this.getArrayResult(tool).length > 0
		},
		isNoteResult(tool) {
			return ['list_notes', 'get_note', 'create_note'].includes(tool.name)
				&& tool.result && this.getArrayResult(tool).length > 0
		},
		isDeckResult(tool) {
			return ['deck_list_stacks', 'deck_get_card', 'deck_create_card',
				'deck_get_board', 'deck_list_boards'].includes(tool.name)
				&& tool.result && this.getArrayResult(tool).length > 0
		},
		isNotificationResult(tool) {
			return ['list_notifications', 'get_notification'].includes(tool.name)
				&& tool.result && this.getArrayResult(tool).length > 0
		},
		isShareResult(tool) {
			return ['list_shares', 'get_share', 'create_share'].includes(tool.name)
				&& tool.result && this.getArrayResult(tool).length > 0
		},
		isGenericResult(tool) {
			return !this.isFileResult(tool) && !this.isCalendarResult(tool)
				&& !this.isSearchResult(tool) && !this.isContactResult(tool)
				&& !this.isTalkResult(tool) && !this.isMailResult(tool)
				&& !this.isNoteResult(tool) && !this.isDeckResult(tool)
				&& !this.isNotificationResult(tool) && !this.isShareResult(tool)
		},
		formatTimestamp(ts) {
			try {
				const d = new Date(ts * 1000)
				return d.toLocaleString('tr-TR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
			} catch { return '' }
		},
		getShareTypeLabel(type) {
			const labels = { 0: 'Kullanıcı', 1: 'Grup', 3: 'Link', 4: 'E-posta', 6: 'Federated' }
			return labels[type] || 'Paylaşım'
		},
		getPermLabel(perms) {
			if (!perms) return ''
			const p = []
			if (perms & 1) p.push('Oku')
			if (perms & 2) p.push('Düzenle')
			if (perms & 4) p.push('Oluştur')
			if (perms & 8) p.push('Sil')
			if (perms & 16) p.push('Paylaş')
			return p.join(', ')
		},
		isTextResult(tool) {
			return typeof tool.result === 'string' && tool.result.length > 0
		},
		formatTextLines(result) {
			const text = typeof result === 'string' ? result : String(result)
			return text.split('\n').filter(l => l.trim()).slice(0, 30)
		},
		getFileIcon(file) {
			if (file.type === 'directory') return FolderIcon
			const mime = file.mime || ''
			if (mime.startsWith('image/')) return FileImageIcon
			if (mime.startsWith('audio/')) return FileMusicIcon
			if (mime.startsWith('video/')) return FileVideoIcon
			if (mime === 'application/pdf') return FilePdfBoxIcon
			if (mime.startsWith('text/')) return FileDocumentIcon
			return FileIcon
		},
		openFile(file) {
			const path = encodeURIComponent(file.filename || '')
			if (file.type === 'directory') {
				window.open(generateUrl('/apps/files/?dir=' + path), '_blank')
			} else {
				window.open(generateUrl('/apps/files/?dir=' + encodeURIComponent(file.filename.substring(0, file.filename.lastIndexOf('/'))) + '&openfile=true&scrollto=' + encodeURIComponent(file.basename)), '_blank')
			}
		},
		getThumbnailUrl(fileid) {
			return generateUrl('/core/preview?fileId=' + fileid + '&x=64&y=64&a=true')
		},
		isImageMime(mime) {
			return mime && mime.startsWith('image/')
		},
		isImageTitle(title) {
			if (!title) return false
			const ext = title.split('.').pop().toLowerCase()
			return ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp'].includes(ext)
		},
		openDeck(card) {
			if (card.id) {
				window.open(generateUrl('/apps/deck/#/board/' + card.id), '_blank')
			}
		},
		isErrorResult(tool) {
			const r = tool.result
			if (typeof r === 'string') {
				return r.startsWith('Error:') || r.startsWith('error:') || r.startsWith('Hata:')
			}
			return false
		},
		openSearchResult(item) {
			if (item.resourceUrl) {
				window.open(item.resourceUrl, '_blank')
			} else if (item.filename) {
				this.openFile(item)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.tool-cards {
	margin-top: 8px;
	margin-left: 2.6em;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;

	&__header {
		display: flex;
		align-items: center;
		gap: 6px;
		padding: 8px 12px;
		background: var(--color-background-dark);
		cursor: pointer;
		font-size: 13px;
		font-weight: 500;
		color: var(--color-text-maxcontrast);

		&:hover {
			background: var(--color-background-hover);
		}
	}

	&__list {
		display: flex;
		flex-direction: column;
		gap: 1px;
		background: var(--color-border-dark);
	}
}

.tool-card {
	background: var(--color-main-background);
	padding: 10px 12px;

	&__header {
		display: flex;
		align-items: center;
		gap: 6px;
		margin-bottom: 4px;
	}

	&__name {
		font-size: 13px;
		text-transform: capitalize;
	}

	&__category {
		font-size: 11px;
		padding: 1px 6px;
		border-radius: 10px;
		background: var(--color-primary-element-light);
		color: var(--color-primary-element);
		text-transform: capitalize;
	}

	&__error-badge {
		font-size: 11px;
		padding: 1px 6px;
		border-radius: 10px;
		background: var(--color-error);
		color: #fff;
	}

	&--error {
		opacity: 0.7;
	}

	&__args {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		margin: 4px 0;
	}

	&__arg {
		font-size: 12px;
		padding: 2px 8px;
		background: var(--color-background-dark);
		border-radius: var(--border-radius);
		color: var(--color-text-maxcontrast);
	}

	&__arg-key {
		font-weight: 500;
		color: var(--color-text-light);
	}

	&__files,
	&__events,
	&__search,
	&__contacts,
	&__talk,
	&__mail,
	&__notes,
	&__deck,
	&__notifs,
	&__shares,
	&__text {
		margin-top: 6px;
		max-height: 250px;
		overflow: auto;
	}

	&__result {
		margin-top: 6px;

		details {
			summary {
				cursor: pointer;
				font-size: 12px;
				color: var(--color-text-maxcontrast);
			}

			pre {
				font-size: 11px;
				max-height: 200px;
				overflow: auto;
				padding: 8px;
				background: var(--color-background-dark);
				border-radius: var(--border-radius);
				margin-top: 4px;
				white-space: pre-wrap;
				word-break: break-word;
			}
		}
	}
}

.file-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 8px;
	border-radius: var(--border-radius);
	cursor: pointer;

	&:hover {
		background: var(--color-background-hover);
	}

	&__icon {
		flex-shrink: 0;
		color: var(--color-primary-element);
	}

	&__thumb {
		width: 32px;
		height: 32px;
		border-radius: var(--border-radius);
		object-fit: cover;
		flex-shrink: 0;
	}

	&__name {
		flex: 1;
		font-size: 13px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__size {
		font-size: 11px;
		color: var(--color-text-maxcontrast);
		flex-shrink: 0;
	}
}

.event-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 8px;
	border-radius: var(--border-radius);
	border-left: 3px solid var(--color-primary-element);
	margin-bottom: 4px;
	background: var(--color-background-dark);

	&__icon {
		color: var(--color-primary-element);
	}

	&__details {
		display: flex;
		flex-direction: column;
		gap: 2px;

		strong {
			font-size: 13px;
		}

		span {
			font-size: 11px;
			color: var(--color-text-maxcontrast);
		}
	}
}

.search-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 8px;
	border-radius: var(--border-radius);
	cursor: pointer;

	&:hover {
		background: var(--color-background-hover);
	}

	&__icon {
		flex-shrink: 0;
		color: var(--color-primary-element);
	}

	&__thumb {
		width: 40px;
		height: 40px;
		border-radius: var(--border-radius);
		object-fit: cover;
		flex-shrink: 0;
	}

	&__details {
		display: flex;
		flex-direction: column;
		gap: 2px;
		overflow: hidden;

		strong {
			font-size: 13px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		span {
			font-size: 11px;
			color: var(--color-text-maxcontrast);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
	}
}

.contact-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 8px;
	border-radius: var(--border-radius);
	border-left: 3px solid var(--color-primary-element);
	margin-bottom: 4px;
	background: var(--color-background-dark);

	&__icon {
		color: var(--color-primary-element);
	}

	&__details {
		display: flex;
		flex-direction: column;
		gap: 2px;

		strong {
			font-size: 13px;
		}

		span {
			font-size: 11px;
			color: var(--color-text-maxcontrast);
		}
	}
}

.talk-item {
	padding: 4px 8px;
	font-size: 13px;

	&--system {
		font-style: italic;
		color: var(--color-text-maxcontrast);
		font-size: 11px;
	}

	&__actor {
		color: var(--color-primary-element);
		margin-right: 4px;
	}

	&__message {
		word-break: break-word;
	}

	&__time {
		font-size: 10px;
		color: var(--color-text-maxcontrast);
		margin-left: 8px;
	}
}

.mail-item {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	padding: 6px 8px;
	border-radius: var(--border-radius);
	border-left: 3px solid transparent;

	&--unread {
		border-left-color: var(--color-primary-element);
		background: var(--color-background-dark);
	}

	&__icon {
		flex-shrink: 0;
		color: var(--color-primary-element);
		margin-top: 2px;
	}

	&__details {
		flex: 1;
		overflow: hidden;
	}

	&__top {
		display: flex;
		align-items: center;
		gap: 4px;

		strong {
			font-size: 13px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
	}

	&__from {
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}

	&__date {
		font-size: 11px;
		color: var(--color-text-maxcontrast);
	}

	&__attach {
		font-size: 12px;
	}
}

.note-item {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	padding: 6px 8px;
	border-radius: var(--border-radius);
	border-left: 3px solid var(--color-warning);
	margin-bottom: 4px;
	background: var(--color-background-dark);

	&__icon {
		color: var(--color-warning);
		flex-shrink: 0;
		margin-top: 2px;
	}

	&__details {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 2px;
		overflow: hidden;

		strong {
			font-size: 13px;
		}
	}

	&__preview {
		font-size: 11px;
		color: var(--color-text-maxcontrast);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__category {
		font-size: 10px;
		color: var(--color-text-maxcontrast);
	}

	&__fav {
		flex-shrink: 0;
	}
}

.deck-item {
	padding: 8px;
	border-radius: var(--border-radius);
	border: 1px solid var(--color-border);
	margin-bottom: 4px;
	background: var(--color-main-background);
	cursor: pointer;

	&:hover {
		background: var(--color-background-hover);
	}

	&--done {
		opacity: 0.6;
		text-decoration: line-through;
	}

	&__labels {
		display: flex;
		gap: 4px;
		margin-bottom: 4px;
		flex-wrap: wrap;
	}

	&__label {
		font-size: 10px;
		padding: 1px 6px;
		border-radius: 10px;
		color: #fff;
	}

	strong {
		font-size: 13px;
		display: block;
	}

	&__desc {
		font-size: 11px;
		color: var(--color-text-maxcontrast);
		display: block;
		margin-top: 2px;
	}

	&__meta {
		display: flex;
		gap: 8px;
		margin-top: 4px;
		font-size: 11px;
		color: var(--color-text-maxcontrast);
	}
}

.notif-item {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	padding: 6px 8px;
	border-radius: var(--border-radius);
	margin-bottom: 4px;

	&__icon {
		color: var(--color-warning);
		flex-shrink: 0;
		margin-top: 2px;
	}

	&__details {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 2px;

		strong {
			font-size: 13px;
		}

		span {
			font-size: 11px;
			color: var(--color-text-maxcontrast);
		}
	}

	&__time {
		font-size: 10px;
	}

	&__app {
		font-size: 10px;
		padding: 1px 6px;
		border-radius: 10px;
		background: var(--color-background-dark);
		color: var(--color-text-maxcontrast);
		flex-shrink: 0;
		align-self: flex-start;
	}
}

.share-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 8px;
	border-radius: var(--border-radius);
	margin-bottom: 4px;

	&__icon {
		color: var(--color-primary-element);
		flex-shrink: 0;
	}

	&__details {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 2px;
		overflow: hidden;

		strong {
			font-size: 13px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		span {
			font-size: 11px;
			color: var(--color-text-maxcontrast);
		}
	}

	&__perms {
		font-size: 10px;
		color: var(--color-text-maxcontrast);
		flex-shrink: 0;
	}
}

.text-line {
	font-size: 12px;
	padding: 1px 8px;
	color: var(--color-text-light);

	&--header {
		font-weight: 600;
		color: var(--color-main-text);
		margin-top: 4px;
	}
}
</style>
