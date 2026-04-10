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
				:class="'tool-card--' + getCategory(tool.name)">
				<div class="tool-card__header">
					<component :is="getCategoryIcon(tool.name)" :size="18" />
					<strong class="tool-card__name">{{ formatName(tool.name) }}</strong>
					<span class="tool-card__category">{{ getCategory(tool.name) }}</span>
				</div>
				<div v-if="Object.keys(tool.args || {}).length" class="tool-card__args">
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
						class="event-item">
						<CalendarIcon :size="16" class="event-item__icon" />
						<div class="event-item__details">
							<strong>{{ event.summary || 'Etkinlik' }}</strong>
							<span v-if="event.start || event.dtstart">{{ formatDate(event.start || event.dtstart) }}</span>
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
				<!-- Generic result preview -->
				<div v-if="isGenericResult(tool) && tool.result"
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
			} catch { return [] }
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
		isGenericResult(tool) {
			return !this.isFileResult(tool) && !this.isCalendarResult(tool)
				&& !this.isSearchResult(tool) && !this.isContactResult(tool)
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

	&__files, &__events {
		margin-top: 6px;
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
</style>
