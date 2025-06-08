<script setup>
import { logStore, navigationStore, sourceStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<div class="viewContainer">
			<!-- Header -->
			<div class="viewHeader">
				<h1 class="viewHeaderTitleIndented">
					{{ t('openconnector', 'Call Logs') }}
				</h1>
				<p>{{ t('openconnector', 'Monitor and analyze API call logs and their performance') }}</p>
			</div>

			<!-- Actions Bar -->
			<div class="viewActionsBar">
				<div class="viewInfo">
					<span class="viewTotalCount">
						{{ t('openconnector', 'Showing {showing} of {total} logs', { showing: paginatedLogs.length, total: filteredLogs.length }) }}
					</span>
					<span v-if="selectedLogs.length > 0" class="viewIndicator">
						({{ t('openconnector', '{count} selected', { count: selectedLogs.length }) }})
					</span>
				</div>
				<div class="viewActions">
					<div class="viewModeSwitchContainer">
						<NcCheckboxRadioSwitch
							v-model="viewMode"
							v-tooltip="'See logs as cards'"
							:button-variant="true"
							value="cards"
							name="view_mode_radio"
							type="radio"
							button-variant-grouped="horizontal">
							Cards
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							v-model="viewMode"
							v-tooltip="'See logs as a table'"
							:button-variant="true"
							value="table"
							name="view_mode_radio"
							type="radio"
							button-variant-grouped="horizontal">
							Table
						</NcCheckboxRadioSwitch>
					</div>

					<NcTextField
						v-model="searchQuery"
						:label="t('openconnector', 'Search logs')"
						:show-trailing-button="searchQuery !== ''"
						trailing-button-icon="close"
						class="searchField"
						@trailing-button-click="searchQuery = ''">
						<Magnify :size="20" />
					</NcTextField>

					<NcActions
						:force-name="true"
						:inline="selectedLogs.length > 0 ? 3 : 2"
						menu-name="Actions">
						<NcActionButton
							v-if="selectedLogs.length > 0"
							type="error"
							close-after-click
							@click="bulkDeleteLogs">
							<template #icon>
								<Delete :size="20" />
							</template>
							{{ t('openconnector', 'Delete ({count})', { count: selectedLogs.length }) }}
						</NcActionButton>
						<NcActionButton
							close-after-click
							@click="clearAllLogs">
							<template #icon>
								<DeleteSweepOutline :size="20" />
							</template>
							Clear All Logs
						</NcActionButton>
						<NcActionButton
							close-after-click
							@click="refreshLogs">
							<template #icon>
								<Refresh :size="20" />
							</template>
							Refresh
						</NcActionButton>
					</NcActions>
				</div>
			</div>

			<!-- Loading, Error, and Empty States -->
			<NcEmptyContent v-if="logStore.loading || logStore.error || !filteredLogs.length"
				:name="emptyContentName"
				:description="emptyContentDescription">
				<template #icon>
					<NcLoadingIcon v-if="logStore.loading" :size="64" />
					<TimelineQuestionOutline v-else :size="64" />
				</template>
			</NcEmptyContent>

			<!-- Content -->
			<div v-else>
				<template v-if="viewMode === 'cards'">
					<div class="cardGrid">
						<div v-for="log in paginatedLogs"
							:key="log.id"
							class="card logCard"
							:class="getLogStatusClass(log)">
							<div class="cardHeader">
								<h2 v-tooltip.bottom="log.statusMessage">
									<TimelineQuestionOutline :size="20" />
									{{ log.statusMessage || 'Unknown' }}
									<span class="statusCode">{{ log.statusCode }}</span>
								</h2>
								<NcActions :primary="true" menu-name="Actions">
									<template #icon>
										<DotsHorizontal :size="20" />
									</template>
									<NcActionButton close-after-click @click="logStore.setViewLogItem(log); navigationStore.setModal('viewCallLog')">
										<template #icon>
											<Eye :size="20" />
										</template>
										View Details
									</NcActionButton>
									<NcActionButton close-after-click @click="deleteLog(log)">
										<template #icon>
											<Delete :size="20" />
										</template>
										Delete
									</NcActionButton>
								</NcActions>
							</div>
							<!-- Log Details -->
							<div class="logDetails">
								<div class="logInfo">
									<div class="logInfoItem">
										<strong>{{ t('openconnector', 'Source') }}:</strong>
										<span>{{ getSourceName(log.sourceId) }}</span>
									</div>
									<div v-if="log.endpoint" class="logInfoItem">
										<strong>{{ t('openconnector', 'Endpoint') }}:</strong>
										<span class="truncatedUrl">{{ log.endpoint }}</span>
									</div>
									<div v-if="log.response?.responseTime" class="logInfoItem">
										<strong>{{ t('openconnector', 'Response Time') }}:</strong>
										<span>{{ (log.response.responseTime / 1000).toFixed(3) }}s</span>
									</div>
									<div class="logInfoItem">
										<strong>{{ t('openconnector', 'Created') }}:</strong>
										<span>{{ new Date(log.created).toLocaleString() }}</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</template>
				<template v-else>
					<div class="viewTableContainer">
						<table class="viewTable">
							<thead>
								<tr>
									<th class="tableColumnCheckbox">
										<NcCheckboxRadioSwitch
											:checked="allSelected"
											:indeterminate="someSelected"
											@update:checked="toggleSelectAll" />
									</th>
									<th>{{ t('openconnector', 'Status') }}</th>
									<th>{{ t('openconnector', 'Source') }}</th>
									<th>{{ t('openconnector', 'Endpoint') }}</th>
									<th>{{ t('openconnector', 'Response Time') }}</th>
									<th>{{ t('openconnector', 'Created') }}</th>
									<th class="tableColumnActions">
										{{ t('openconnector', 'Actions') }}
									</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="log in paginatedLogs"
									:key="log.id"
									class="viewTableRow"
									:class="[
										{ viewTableRowSelected: selectedLogs.includes(log.id) },
										getLogStatusClass(log)
									]">
									<td class="tableColumnCheckbox">
										<NcCheckboxRadioSwitch
											:checked="selectedLogs.includes(log.id)"
											@update:checked="(checked) => toggleLogSelection(log.id, checked)" />
									</td>
									<td class="statusColumn">
										<span class="statusInfo">
											<span class="statusCode">{{ log.statusCode }}</span>
											<span class="statusMessage">{{ log.statusMessage || 'Unknown' }}</span>
										</span>
									</td>
									<td>{{ getSourceName(log.sourceId) }}</td>
									<td class="tableColumnConstrained">
										<span v-if="log.endpoint" class="truncatedUrl">{{ log.endpoint }}</span>
										<span v-else>-</span>
									</td>
									<td>
										<span v-if="log.response?.responseTime">{{ (log.response.responseTime / 1000).toFixed(3) }}s</span>
										<span v-else>-</span>
									</td>
									<td>{{ new Date(log.created).toLocaleString() }}</td>
									<td class="tableColumnActions">
										<NcActions :primary="false">
											<template #icon>
												<DotsHorizontal :size="20" />
											</template>
											<NcActionButton close-after-click @click="logStore.setViewLogItem(log); navigationStore.setModal('viewCallLog')">
												<template #icon>
													<Eye :size="20" />
												</template>
												View Details
											</NcActionButton>
											<NcActionButton close-after-click @click="deleteLog(log)">
												<template #icon>
													<Delete :size="20" />
												</template>
												Delete
											</NcActionButton>
										</NcActions>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</template>
			</div>

			<!-- Pagination -->
			<PaginationComponent
				v-if="filteredLogs.length > 0"
				:current-page="pagination.page || 1"
				:total-pages="Math.ceil(filteredLogs.length / (pagination.limit || 20))"
				:total-items="filteredLogs.length"
				:current-page-size="pagination.limit || 20"
				:min-items-to-show="10"
				@page-changed="onPageChanged"
				@page-size-changed="onPageSizeChanged" />
		</div>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcLoadingIcon, NcActions, NcActionButton, NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import TimelineQuestionOutline from 'vue-material-design-icons/TimelineQuestionOutline.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import DeleteSweepOutline from 'vue-material-design-icons/DeleteSweepOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'

import PaginationComponent from '../../components/PaginationComponent.vue'

export default {
	name: 'CallLogIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		NcActions,
		NcActionButton,
		NcCheckboxRadioSwitch,
		NcTextField,
		TimelineQuestionOutline,
		DotsHorizontal,
		Delete,
		DeleteSweepOutline,
		Refresh,
		Eye,
		Magnify,
		PaginationComponent,
	},
	data() {
		return {
			viewMode: 'cards',
			selectedLogs: [],
			searchQuery: '',
			pagination: {
				page: 1,
				limit: 20,
			},
		}
	},
	computed: {
		filteredLogs() {
			if (!logStore.logList) return []

			return logStore.logList.filter(log => {
				if (!this.searchQuery) return true
				const query = this.searchQuery.toLowerCase()
				return (log.statusMessage && log.statusMessage.toLowerCase().includes(query))
					   || (log.endpoint && log.endpoint.toLowerCase().includes(query))
					   || (log.statusCode && log.statusCode.toString().includes(query))
					   || (this.getSourceName(log.sourceId).toLowerCase().includes(query))
			})
		},
		paginatedLogs() {
			const start = ((this.pagination.page || 1) - 1) * (this.pagination.limit || 20)
			const end = start + (this.pagination.limit || 20)
			return this.filteredLogs.slice(start, end)
		},
		allSelected() {
			return this.filteredLogs.length > 0 && this.filteredLogs.every(log => this.selectedLogs.includes(log.id))
		},
		someSelected() {
			return this.selectedLogs.length > 0 && !this.allSelected
		},
		emptyContentName() {
			if (logStore.loading) {
				return t('openconnector', 'Loading logs...')
			} else if (logStore.error) {
				return logStore.error
			} else if (!logStore.logList?.length) {
				return t('openconnector', 'No logs found')
			} else if (!this.filteredLogs.length) {
				return t('openconnector', 'No logs match your search')
			}
			return ''
		},
		emptyContentDescription() {
			if (logStore.loading) {
				return t('openconnector', 'Please wait while we fetch your logs.')
			} else if (logStore.error) {
				return t('openconnector', 'Please try again later.')
			} else if (!logStore.logList?.length) {
				return t('openconnector', 'No call logs are available.')
			} else if (!this.filteredLogs.length) {
				return t('openconnector', 'Try adjusting your search terms.')
			}
			return ''
		},
	},
	mounted() {
		logStore.refreshLogList()
		sourceStore.refreshSourceList()
	},
	methods: {
		toggleSelectAll(checked) {
			if (checked) {
				this.selectedLogs = this.filteredLogs.map(log => log.id)
			} else {
				this.selectedLogs = []
			}
		},
		toggleLogSelection(logId, checked) {
			if (checked) {
				this.selectedLogs.push(logId)
			} else {
				this.selectedLogs = this.selectedLogs.filter(id => id !== logId)
			}
		},
		onPageChanged(page) {
			this.pagination.page = page
		},
		onPageSizeChanged(pageSize) {
			this.pagination.page = 1
			this.pagination.limit = pageSize
		},
		getSourceName(sourceId) {
			if (!sourceId) return t('openconnector', 'Unknown Source')

			const source = sourceStore.sourceList?.find(s => s.id === sourceId)
			return source?.name || `Source ${sourceId}`
		},
		getLogStatusClass(log) {
			if (!log.statusCode) return 'unknownStatus'
			if (log.statusCode >= 200 && log.statusCode < 300) return 'successStatus'
			if (log.statusCode >= 400 && log.statusCode < 500) return 'clientErrorStatus'
			if (log.statusCode >= 500) return 'serverErrorStatus'
			return 'infoStatus'
		},
		deleteLog(log) {
			// Set the log item and open delete dialog
			logStore.setViewLogItem(log)
			navigationStore.setDialog('deleteLog')
		},
		bulkDeleteLogs() {
			if (this.selectedLogs.length === 0) return

			// Set selected logs and open bulk delete dialog
			logStore.setSelectedLogs(this.selectedLogs)
			navigationStore.setDialog('bulkDeleteLogs')
		},
		clearAllLogs() {
			// Open confirm dialog for clearing all logs
			navigationStore.setDialog('clearAllLogs')
		},
		refreshLogs() {
			logStore.refreshLogList()
			this.selectedLogs = []
		},
	},
}
</script>

<style scoped>
.searchField {
	min-width: 200px;
	margin-right: 8px;
}

.logCard {
	border-left: 4px solid var(--color-border);
}

.logCard.successStatus {
	border-left-color: #69b090;
}

.logCard.clientErrorStatus {
	border-left-color: #e9322d;
}

.logCard.serverErrorStatus {
	border-left-color: #e9322d;
}

.logCard.infoStatus {
	border-left-color: #0082c9;
}

.logCard.unknownStatus {
	border-left-color: var(--color-text-maxcontrast);
}

.statusCode {
	font-weight: bold;
	padding: 2px 6px;
	border-radius: 4px;
	font-size: 0.85em;
	margin-left: 8px;
}

.successStatus .statusCode {
	background-color: #69b090;
	color: white;
}

.clientErrorStatus .statusCode,
.serverErrorStatus .statusCode {
	background-color: #e9322d;
	color: white;
}

.infoStatus .statusCode {
	background-color: #0082c9;
	color: white;
}

.unknownStatus .statusCode {
	background-color: var(--color-text-maxcontrast);
	color: white;
}

.logDetails {
	margin-top: 1rem;
}

.logInfo {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.logInfoItem {
	display: flex;
	gap: 0.5rem;
}

.logInfoItem strong {
	min-width: 120px;
}

.truncatedUrl {
	max-width: 300px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	display: inline-block;
}

.statusColumn {
	min-width: 200px;
}

.statusInfo {
	display: flex;
	align-items: center;
	gap: 8px;
}

.statusMessage {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* Row status styling for table view */
.viewTableRow.successStatus {
	background-color: rgba(105, 176, 144, 0.1);
}

.viewTableRow.clientErrorStatus,
.viewTableRow.serverErrorStatus {
	background-color: rgba(233, 50, 45, 0.1);
}

.viewTableRow.infoStatus {
	background-color: rgba(0, 130, 201, 0.1);
}
</style>
