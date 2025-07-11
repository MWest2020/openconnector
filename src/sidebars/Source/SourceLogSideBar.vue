<script setup>
import { logStore, navigationStore, sourceStore } from '../../store/store.js'
</script>

<template>
	<NcAppSidebar
		ref="sidebar"
		v-model="activeTab"
		:name="t('openconnector', 'Source Log Management')"
		:subtitle="t('openconnector', 'Filter and manage source call logs')"
		:subname="t('openconnector', 'Export, view, or delete call logs')"
		:open="navigationStore.sidebarState.sourceLogs"
		@update:open="(e) => navigationStore.setSidebarState('sourceLogs', e)">
		<NcAppSidebarTab id="filters-tab" :name="t('openconnector', 'Filters')" :order="1">
			<template #icon>
				<FilterOutline :size="20" />
			</template>

			<!-- Filter Section -->
			<div class="filterSection">
				<h3>{{ t('openconnector', 'Filter Call Logs') }}</h3>
				<div class="filterGroup">
					<label for="sourceSelect">{{ t('openconnector', 'Source') }}</label>
					<NcSelect
						id="sourceSelect"
						v-model="selectedSource"
						:options="sourceOptions"
						:placeholder="t('openconnector', 'All sources')"
						:input-label="t('openconnector', 'Source')"
						:clearable="true"
						@input="handleSourceChange" />
				</div>
				<div class="filterGroup">
					<label for="statusSelect">{{ t('openconnector', 'Status Codes') }}</label>
					<NcSelect
						id="statusSelect"
						v-model="selectedStatusCodes"
						:options="statusCodeOptions"
						:placeholder="t('openconnector', 'All status codes')"
						:input-label="t('openconnector', 'Status Codes')"
						:multiple="true"
						:clearable="true"
						@input="applyFilters">
						<template #option="{ option }">
							{{ option && option.label ? option.label : option }}
						</template>
					</NcSelect>
				</div>
				<div class="filterGroup">
					<label for="methodSelect">{{ t('openconnector', 'HTTP Methods') }}</label>
					<NcSelect
						id="methodSelect"
						v-model="selectedMethods"
						:options="methodOptions"
						:placeholder="t('openconnector', 'All methods')"
						:input-label="t('openconnector', 'HTTP Methods')"
						:multiple="true"
						:clearable="true"
						@input="applyFilters">
						<template #option="{ option }">
							{{ option && option.label ? option.label : option }}
						</template>
					</NcSelect>
				</div>
				<div class="filterGroup">
					<label>{{ t('openconnector', 'Date Range') }}</label>
					<NcDateTimePickerNative
						id="dateFromPicker"
						v-model="dateFrom"
						:label="t('openconnector', 'From date')"
						type="datetime-local"
						@input="applyFilters" />
					<NcDateTimePickerNative
						id="dateToPicker"
						v-model="dateTo"
						:label="t('openconnector', 'To date')"
						type="datetime-local"
						@input="applyFilters" />
				</div>
				<div class="filterGroup">
					<label for="endpointFilter">{{ t('openconnector', 'Endpoint') }}</label>
					<NcTextField
						id="endpointFilter"
						v-model="endpointFilter"
						:label="t('openconnector', 'Filter by endpoint')"
						:placeholder="t('openconnector', 'Enter endpoint URL')"
						@update:value="handleEndpointFilterChange" />
				</div>
				<div class="filterGroup">
					<NcCheckboxRadioSwitch
						v-model="showOnlyErrors"
						@update:checked="applyFilters">
						{{ t('openconnector', 'Show only errors (4xx, 5xx)') }}
					</NcCheckboxRadioSwitch>
				</div>
				<div class="filterGroup">
					<NcCheckboxRadioSwitch
						v-model="showSlowRequests"
						@update:checked="applyFilters">
						{{ t('openconnector', 'Show slow requests (>5s)') }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>

			<div class="actionGroup">
				<NcButton @click="clearFilters">
					<template #icon>
						<FilterOffOutline :size="20" />
					</template>
					{{ t('openconnector', 'Clear Filters') }}
				</NcButton>
			</div>

			<NcNoteCard type="info" class="filter-hint">
				{{ t('openconnector', 'Use filters to narrow down call logs by source, status code, HTTP method, date range, or endpoint.') }}
			</NcNoteCard>
		</NcAppSidebarTab>

		<NcAppSidebarTab id="stats-tab" :name="t('openconnector', 'Statistics')" :order="2">
			<template #icon>
				<ChartLine :size="20" />
			</template>

			<!-- Statistics Section -->
			<div class="statsSection">
				<h3>{{ t('openconnector', 'Call Log Statistics') }}</h3>
				<div class="statCard">
					<div class="statNumber">
						{{ totalLogs }}
					</div>
					<div class="statLabel">
						{{ t('openconnector', 'Total Call Logs') }}
					</div>
				</div>
				<div class="statCard success">
					<div class="statNumber">
						{{ successCount }}
					</div>
					<div class="statLabel">
						{{ t('openconnector', 'Successful Calls (2xx)') }}
					</div>
				</div>
				<div class="statCard error">
					<div class="statNumber">
						{{ errorCount }}
					</div>
					<div class="statLabel">
						{{ t('openconnector', 'Failed Calls (4xx, 5xx)') }}
					</div>
				</div>
				<div class="statCard">
					<div class="statNumber">
						{{ averageResponseTime }}s
					</div>
					<div class="statLabel">
						{{ t('openconnector', 'Average Response Time') }}
					</div>
				</div>
			</div>

			<!-- Status Code Distribution -->
			<div class="statusDistribution">
				<h4>{{ t('openconnector', 'Status Code Distribution') }}</h4>
				<NcListItem v-for="(status, index) in statusDistribution"
					:key="index"
					:name="`${status.code} - ${status.message}`"
					:bold="false">
					<template #icon>
						<CheckCircle v-if="status.code >= 200 && status.code < 300" :size="32" />
						<AlertCircle v-else-if="status.code >= 400 && status.code < 500" :size="32" />
						<CloseCircle v-else-if="status.code >= 500" :size="32" />
						<InformationOutline v-else :size="32" />
					</template>
					<template #subname>
						{{ t('openconnector', '{count} calls', { count: status.count }) }}
					</template>
				</NcListItem>
			</div>

			<!-- Top Sources -->
			<div class="topSources">
				<h4>{{ t('openconnector', 'Most Active Sources') }}</h4>
				<NcListItem v-for="(source, index) in topSources"
					:key="index"
					:name="source.name"
					:bold="false">
					<template #icon>
						<DatabaseArrowLeftOutline :size="32" />
					</template>
					<template #subname>
						{{ t('openconnector', '{count} calls', { count: source.count }) }}
					</template>
				</NcListItem>
			</div>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import {
	NcAppSidebar,
	NcAppSidebarTab,
	NcSelect,
	NcNoteCard,
	NcButton,
	NcListItem,
	NcDateTimePickerNative,
	NcTextField,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'
import FilterOutline from 'vue-material-design-icons/FilterOutline.vue'
import ChartLine from 'vue-material-design-icons/ChartLine.vue'
import DatabaseArrowLeftOutline from 'vue-material-design-icons/DatabaseArrowLeftOutline.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import CloseCircle from 'vue-material-design-icons/CloseCircle.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import FilterOffOutline from 'vue-material-design-icons/FilterOffOutline.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'SourceLogSideBar',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		NcSelect,
		NcNoteCard,
		NcButton,
		NcListItem,
		NcDateTimePickerNative,
		NcTextField,
		NcCheckboxRadioSwitch,
		FilterOutline,
		ChartLine,
		DatabaseArrowLeftOutline,
		CheckCircle,
		AlertCircle,
		CloseCircle,
		InformationOutline,
		FilterOffOutline,
	},
	data() {
		return {
			activeTab: 'filters-tab',
			selectedSource: null,
			selectedStatusCodes: [],
			selectedMethods: [],
			dateFrom: null,
			dateTo: null,
			endpointFilter: '',
			showOnlyErrors: false,
			showSlowRequests: false,
			filteredCount: 0,
			totalLogs: 0,
			successCount: 0,
			errorCount: 0,
			averageResponseTime: 0,
			statusDistribution: [],
			topSources: [],
			filterTimeout: null,
		}
	},
	computed: {
		statusCodeOptions() {
			return [
				{ label: '200 - OK', value: '200' },
				{ label: '201 - Created', value: '201' },
				{ label: '400 - Bad Request', value: '400' },
				{ label: '401 - Unauthorized', value: '401' },
				{ label: '403 - Forbidden', value: '403' },
				{ label: '404 - Not Found', value: '404' },
				{ label: '500 - Internal Server Error', value: '500' },
				{ label: '502 - Bad Gateway', value: '502' },
				{ label: '503 - Service Unavailable', value: '503' },
			]
		},
		methodOptions() {
			return [
				{ label: 'GET', value: 'GET' },
				{ label: 'POST', value: 'POST' },
				{ label: 'PUT', value: 'PUT' },
				{ label: 'PATCH', value: 'PATCH' },
				{ label: 'DELETE', value: 'DELETE' },
			]
		},
		sourceOptions() {
			return sourceStore.sourceList?.map(source => ({
				value: source,
				label: source.name,
				title: source.name,
			})) || []
		},
		selectedSourceValue() {
			if (!sourceStore.sourceItem) return null
			return sourceStore.sourceList?.find(s => s.id === sourceStore.sourceItem.id) || null
		},
	},
	watch: {
		'sourceStore.sourceItem'() {
			this.selectedSource = this.selectedSourceValue
			this.applyFilters()
		},
	},
	mounted() {
		// Load required data
		if (!sourceStore.sourceList?.length) {
			sourceStore.refreshSourceList()
		}

		// Load initial log data
		this.loadLogData()
		this.loadStatistics()
		this.loadStatusDistribution()
		this.loadTopSources()

		// Listen for filtered count updates
		this.$root.$on('source-log-filtered-count', (count) => {
			this.filteredCount = count
		})

		// Watch store changes and update count
		this.updateFilteredCount()

		this.selectedSource = this.selectedSourceValue
	},
	beforeDestroy() {
		this.$root.$off('source-log-filtered-count')
	},
	methods: {
		/**
		 * Load call log data and update filtered count
		 */
		async loadLogData() {
			try {
				await sourceStore.refreshSourceLogs()
				this.updateFilteredCount()
			} catch (error) {
				console.error('Error loading log data:', error)
			}
		},
		/**
		 * Clear all filters
		 */
		clearAllFilters() {
			// Clear component state
			this.selectedStatusCodes = []
			this.selectedMethods = []
			this.dateFrom = null
			this.dateTo = null
			this.endpointFilter = ''
			this.showOnlyErrors = false
			this.showSlowRequests = false

			// Clear global stores
			sourceStore.setSourceItem(null)

			// Clear store filters
			logStore.setLogFilters({})

			// Refresh without applying filters
			sourceStore.refreshSourceLogs()
		},
		/**
		 * Clear filters (alias for clearAllFilters for template compatibility)
		 */
		clearFilters() {
			this.clearAllFilters()
		},
		/**
		 * Handle endpoint filter change with debouncing
		 * @param value
		 */
		handleEndpointFilterChange(value) {
			this.endpointFilter = value
			this.debouncedApplyFilters()
		},
		/**
		 * Apply filters and emit to parent components
		 */
		applyFilters() {
			const filters = {}

			// Build status code filter
			if (Array.isArray(this.selectedStatusCodes) && this.selectedStatusCodes.length > 0) {
				const statusCodes = this.selectedStatusCodes.filter(s => s && s.value).map(s => s.value)
				if (statusCodes.length > 0) {
					filters.statusCode = statusCodes.join(',')
				}
			}

			// Build method filter
			if (Array.isArray(this.selectedMethods) && this.selectedMethods.length > 0) {
				const methods = this.selectedMethods.filter(m => m && m.value).map(m => m.value)
				if (methods.length > 0) {
					filters.method = methods.join(',')
				}
			}

			// Build source filter
			if (this.selectedSource && this.selectedSource.value) {
				filters.source_id = this.selectedSource.value.id.toString()
			}

			// Date filters
			if (this.dateFrom) {
				filters.dateFrom = this.dateFrom
			}
			if (this.dateTo) {
				filters.dateTo = this.dateTo
			}

			// Endpoint filter
			if (this.endpointFilter) {
				filters.endpoint = this.endpointFilter
			}

			// Error filter
			if (this.showOnlyErrors) {
				filters.onlyErrors = true
			}

			// Slow requests filter
			if (this.showSlowRequests) {
				filters.slowRequests = true
			}

			// Set filters in store and refresh data
			logStore.setLogFilters(filters)
			sourceStore.refreshSourceLogs(filters)

			// Also emit for legacy compatibility
			this.$root.$emit('source-log-filters-changed', filters)
		},
		/**
		 * Debounced version of applyFilters for text input
		 */
		debouncedApplyFilters() {
			clearTimeout(this.filterTimeout)
			this.filterTimeout = setTimeout(() => {
				this.applyFilters()
			}, 500)
		},
		/**
		 * Update filtered count from store
		 */
		updateFilteredCount() {
			const logs = (sourceStore.sourceLogs && Array.isArray(sourceStore.sourceLogs.results)) ? sourceStore.sourceLogs.results : []
			this.filteredCount = logs.length
			this.totalLogs = logs.length
		},
		/**
		 * Load statistics
		 */
		async loadStatistics() {
			try {
				const logs = (sourceStore.sourceLogs && Array.isArray(sourceStore.sourceLogs.results)) ? sourceStore.sourceLogs.results : []
				this.totalLogs = logs.length
				this.successCount = logs.filter(log => log.statusCode >= 200 && log.statusCode < 300).length
				this.errorCount = logs.filter(log => log.statusCode >= 400).length
				const responseTimes = logs.filter(log => log.response?.responseTime).map(log => log.response.responseTime / 1000)
				this.averageResponseTime = responseTimes.length > 0 ? (responseTimes.reduce((sum, time) => sum + time, 0) / responseTimes.length).toFixed(3) : 0
			} catch (error) {
				console.error('Error loading statistics:', error)
			}
		},
		/**
		 * Load status code distribution for stats
		 */
		async loadStatusDistribution() {
			try {
				const logs = (sourceStore.sourceLogs && Array.isArray(sourceStore.sourceLogs.results)) ? sourceStore.sourceLogs.results : []
				const statusMap = {}
				logs.forEach(log => {
					const code = log.statusCode
					if (!statusMap[code]) {
						statusMap[code] = {
							code,
							message: log.statusMessage || 'Unknown',
							count: 0,
						}
					}
					statusMap[code].count++
				})
				this.statusDistribution = Object.values(statusMap).sort((a, b) => b.count - a.count).slice(0, 10)
			} catch (error) {
				console.error('Error loading status distribution:', error)
			}
		},
		/**
		 * Load top sources for stats
		 */
		async loadTopSources() {
			try {
				const logs = (sourceStore.sourceLogs && Array.isArray(sourceStore.sourceLogs.results)) ? sourceStore.sourceLogs.results : []
				const sourceMap = {}
				logs.forEach(log => {
					const sourceId = log.sourceId
					if (!sourceMap[sourceId]) {
						const source = sourceStore.sourceList?.find(s => s.id === sourceId)
						sourceMap[sourceId] = {
							name: source?.name || `Source ${sourceId}`,
							count: 0,
						}
					}
					sourceMap[sourceId].count++
				})
				this.topSources = Object.values(sourceMap).sort((a, b) => b.count - a.count).slice(0, 10)
			} catch (error) {
				console.error('Error loading top sources:', error)
			}
		},
		/**
		 * Handle source change
		 * @param source
		 * @param sourceOption
		 */
		handleSourceChange(sourceOption) {
			const source = sourceOption && sourceOption.value ? sourceOption.value : null
			sourceStore.setSourceItem(source)
			this.applyFilters()
		},
	},
}
</script>

<style scoped>
.filterSection,
.statsSection {
	padding: 12px 0;
	border-bottom: 1px solid var(--color-border);
}

.filterSection:last-child,
.statsSection:last-child {
	border-bottom: none;
}

.filterSection h3,
.statsSection h3 {
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	font-weight: bold;
	padding: 0 16px;
	margin: 0 0 12px 0;
}

.filterGroup {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 0 16px;
	margin-bottom: 16px;
}

.filterGroup label {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}

.actionGroup {
	padding: 0 16px;
	margin-bottom: 12px;
}

.filter-hint {
	margin: 8px 16px;
}

.statsSection {
	padding: 16px;
}

.statCard {
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
	padding: 16px;
	margin-bottom: 12px;
	text-align: center;
}

.statCard.success {
	border-left: 4px solid var(--color-success);
}

.statCard.error {
	border-left: 4px solid var(--color-error);
}

.statNumber {
	font-size: 2rem;
	font-weight: bold;
	color: var(--color-primary);
	margin-bottom: 4px;
}

.statLabel {
	font-size: 0.9rem;
	color: var(--color-text-maxcontrast);
}

.statusDistribution,
.topSources {
	margin-top: 20px;
}

.statusDistribution h4,
.topSources h4 {
	margin: 0 0 12px 0;
	font-size: 1rem;
	font-weight: 500;
	color: var(--color-main-text);
}

/* Add some spacing between select inputs */
:deep(.v-select) {
	margin-bottom: 8px;
}
</style>
