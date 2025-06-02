<script setup>
import { contractStore, synchronizationStore } from '../../store/store.js'
</script>

<template>
	<div class="sidebar">
		<div class="sidebar-header">
			<h3>{{ t('openconnector', 'Contracts') }}</h3>
			<p>{{ t('openconnector', 'Filter and manage contracts') }}</p>
		</div>

		<!-- Filters Section -->
		<div class="section">
			<h4>{{ t('openconnector', 'Filters') }}</h4>
			
			<!-- Synchronization Filter -->
			<div class="filter-group">
				<label>{{ t('openconnector', 'Synchronization') }}</label>
				<NcSelect
					v-model="filters.synchronization"
					:options="synchronizationOptions"
					:placeholder="t('openconnector', 'All synchronizations')"
					:input-label="t('openconnector', 'Synchronization')"
					:clearable="true"
					@input="applyFilters" />
			</div>

			<!-- Status Filter -->
			<div class="filter-group">
				<label>{{ t('openconnector', 'Status') }}</label>
				<NcSelect
					v-model="filters.status"
					:options="statusOptions"
					:placeholder="t('openconnector', 'All statuses')"
					:input-label="t('openconnector', 'Status')"
					:clearable="true"
					@input="applyFilters" />
			</div>

			<!-- Date Range Filter -->
			<div class="filter-group">
				<label>{{ t('openconnector', 'Last Executed') }}</label>
				<div class="date-range">
					<NcDateTimePickerNative
						id="contracts-date-from"
						v-model="filters.dateFrom"
						type="date"
						:placeholder="t('openconnector', 'From')"
						@input="applyFilters" />
					<NcDateTimePickerNative
						id="contracts-date-to"
						v-model="filters.dateTo"
						type="date"
						:placeholder="t('openconnector', 'To')"
						@input="applyFilters" />
				</div>
			</div>

			<!-- Success Rate Filter -->
			<div class="filter-group">
				<label>{{ t('openconnector', 'Success Rate') }}</label>
				<div class="range-inputs">
					<NcTextField
						v-model="filters.successRateMin"
						type="number"
						:placeholder="t('openconnector', 'Min %')"
						min="0"
						max="100"
						@input="debouncedApplyFilters" />
					<NcTextField
						v-model="filters.successRateMax"
						type="number"
						:placeholder="t('openconnector', 'Max %')"
						min="0"
						max="100"
						@input="debouncedApplyFilters" />
				</div>
			</div>

			<!-- Clear Filters -->
			<NcButton v-if="hasActiveFilters" @click="clearFilters">
				{{ t('openconnector', 'Clear Filters') }}
			</NcButton>
		</div>

		<!-- Bulk Actions Section -->
		<div v-if="selectedCount > 0" class="section">
			<h4>{{ t('openconnector', 'Bulk Actions') }}</h4>
			<p class="selection-info">
				{{ t('openconnector', '{count} contracts selected', { count: selectedCount }) }}
			</p>
			<div class="bulk-actions">
				<NcButton type="primary" @click="bulkActivate">
					<template #icon>
						<Play :size="20" />
					</template>
					{{ t('openconnector', 'Activate Selected') }}
				</NcButton>
				<NcButton type="error" @click="bulkDeactivate">
					<template #icon>
						<Pause :size="20" />
					</template>
					{{ t('openconnector', 'Deactivate Selected') }}
				</NcButton>
			</div>
		</div>

		<!-- Statistics Section -->
		<div class="section">
			<h4>{{ t('openconnector', 'Statistics') }}</h4>
			<div v-if="statisticsLoading" class="loading-small">
				<NcLoadingIcon :size="24" />
			</div>
			<div v-else class="stats-grid">
				<div class="stat-item">
					<span class="stat-label">{{ t('openconnector', 'Total Contracts') }}</span>
					<span class="stat-value">{{ filteredCount }}</span>
				</div>
				<div class="stat-item">
					<span class="stat-label">{{ t('openconnector', 'Active') }}</span>
					<span class="stat-value success">{{ statistics.activeCount || 0 }}</span>
				</div>
				<div class="stat-item">
					<span class="stat-label">{{ t('openconnector', 'Inactive') }}</span>
					<span class="stat-value warning">{{ statistics.inactiveCount || 0 }}</span>
				</div>
				<div class="stat-item">
					<span class="stat-label">{{ t('openconnector', 'Errors') }}</span>
					<span class="stat-value error">{{ statistics.errorCount || 0 }}</span>
				</div>
			</div>

			<!-- Performance Chart -->
			<div v-if="statistics.performanceData" class="chart-container">
				<h5>{{ t('openconnector', 'Performance Trends') }}</h5>
				<div class="performance-chart">
					<div v-for="(data, period) in statistics.performanceData" 
						:key="period" 
						class="performance-bar">
						<div class="period-label">{{ formatPeriod(period) }}</div>
						<div class="performance-progress">
							<div class="performance-fill" 
								:style="{ width: getPerformancePercentage(data.successRate) + '%' }"></div>
						</div>
						<div class="performance-rate">{{ data.successRate }}%</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Export Section -->
		<div class="section">
			<h4>{{ t('openconnector', 'Export') }}</h4>
			<NcButton @click="exportFiltered">
				<template #icon>
					<Download :size="20" />
				</template>
				{{ t('openconnector', 'Export Filtered Contracts') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import {
	NcSelect,
	NcTextField,
	NcButton,
	NcLoadingIcon,
	NcDateTimePickerNative,
} from '@nextcloud/vue'
import Play from 'vue-material-design-icons/Play.vue'
import Pause from 'vue-material-design-icons/Pause.vue'
import Download from 'vue-material-design-icons/Download.vue'

export default {
	name: 'ContractsSideBar',
	components: {
		NcSelect,
		NcTextField,
		NcButton,
		NcLoadingIcon,
		NcDateTimePickerNative,
		Play,
		Pause,
		Download,
	},
	data() {
		return {
			filters: {
				synchronization: null,
				status: null,
				dateFrom: null,
				dateTo: null,
				successRateMin: '',
				successRateMax: '',
			},
			selectedCount: 0,
			filteredCount: 0,
			statistics: {},
			statisticsLoading: false,
			debounceTimer: null,
		}
	},
	computed: {
		/**
		 * Get synchronization filter options
		 * @return {Array} Array of synchronization options
		 */
		synchronizationOptions() {
			return synchronizationStore.synchronizationList.map(sync => ({
				id: sync.id,
				label: sync.name || `Synchronization ${sync.id}`,
			}))
		},
		/**
		 * Get status filter options
		 * @return {Array} Array of status options
		 */
		statusOptions() {
			return [
				{ id: 'active', label: this.t('openconnector', 'Active') },
				{ id: 'inactive', label: this.t('openconnector', 'Inactive') },
				{ id: 'error', label: this.t('openconnector', 'Error') },
			]
		},
		/**
		 * Check if any filters are active
		 * @return {boolean} Whether any filters are active
		 */
		hasActiveFilters() {
			return Object.values(this.filters).some(value => value !== null && value !== '')
		},
	},
	async mounted() {
		// Load initial statistics and performance data
		await this.loadStatistics()
		await this.loadPerformance()

		// Listen for events from main view
		this.$root.$on('contracts-selection-count', this.updateSelectionCount)
		this.$root.$on('contracts-filtered-count', this.updateFilteredCount)
	},
	beforeDestroy() {
		this.$root.$off('contracts-selection-count')
		this.$root.$off('contracts-filtered-count')
		
		if (this.debounceTimer) {
			clearTimeout(this.debounceTimer)
		}
	},
	methods: {
		/**
		 * Load statistics data
		 * @return {Promise<void>}
		 */
		async loadStatistics() {
			this.statisticsLoading = true
			try {
				await contractStore.fetchStatistics()
				this.statistics = contractStore.contractsStatistics
			} catch (error) {
				console.error('Error loading statistics:', error)
				// Set default empty statistics to prevent errors
				this.statistics = {
					activeCount: 0,
					inactiveCount: 0,
					errorCount: 0,
				}
			} finally {
				this.statisticsLoading = false
			}
		},
		/**
		 * Load performance data
		 * @return {Promise<void>}
		 */
		async loadPerformance() {
			try {
				await contractStore.fetchPerformance()
				// Merge performance data into statistics
				this.statistics = {
					...this.statistics,
					performanceData: contractStore.contractsPerformance,
				}
			} catch (error) {
				console.error('Error loading performance data:', error)
			}
		},
		/**
		 * Apply filters with debouncing for number inputs
		 * @return {void}
		 */
		debouncedApplyFilters() {
			if (this.debounceTimer) {
				clearTimeout(this.debounceTimer)
			}
			this.debounceTimer = setTimeout(() => {
				this.applyFilters()
			}, 500)
		},
		/**
		 * Apply current filters
		 * @return {void}
		 */
		applyFilters() {
			// Clean up empty values
			const cleanFilters = {}
			Object.entries(this.filters).forEach(([key, value]) => {
				if (value !== null && value !== '') {
					cleanFilters[key] = value
				}
			})

			// Emit filters to main view
			this.$root.$emit('contracts-filters-changed', cleanFilters)
		},
		/**
		 * Clear all filters
		 * @return {void}
		 */
		clearFilters() {
			this.filters = {
				synchronization: null,
				status: null,
				dateFrom: null,
				dateTo: null,
				successRateMin: '',
				successRateMax: '',
			}
			this.applyFilters()
		},
		/**
		 * Update selection count from main view
		 * @param {number} count - Number of selected items
		 * @return {void}
		 */
		updateSelectionCount(count) {
			this.selectedCount = count
		},
		/**
		 * Update filtered count from main view
		 * @param {number} count - Number of filtered items
		 * @return {void}
		 */
		updateFilteredCount(count) {
			this.filteredCount = count
		},
		/**
		 * Trigger bulk activate action
		 * @return {void}
		 */
		bulkActivate() {
			this.$root.$emit('contracts-bulk-activate')
		},
		/**
		 * Trigger bulk deactivate action
		 * @return {void}
		 */
		bulkDeactivate() {
			this.$root.$emit('contracts-bulk-deactivate')
		},
		/**
		 * Trigger export filtered action
		 * @return {void}
		 */
		exportFiltered() {
			this.$root.$emit('contracts-export-filtered')
		},
		/**
		 * Format period for display
		 * @param {string} period - Period identifier
		 * @return {string} Formatted period
		 */
		formatPeriod(period) {
			// Convert period like 'last_7_days' to 'Last 7 Days'
			return period.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
		},
		/**
		 * Get percentage for performance display
		 * @param {number} successRate - Success rate percentage
		 * @return {number} Percentage
		 */
		getPerformancePercentage(successRate) {
			return Math.min(100, Math.max(0, successRate || 0))
		},
	},
}
</script>

<style scoped>
.sidebar {
	padding: 20px;
	background: var(--color-main-background);
	border-left: 1px solid var(--color-border);
	height: 100%;
	overflow-y: auto;
}

.sidebar-header {
	margin-bottom: 30px;
}

.sidebar-header h3 {
	margin: 0 0 10px 0;
	font-size: 1.5rem;
	font-weight: 300;
}

.sidebar-header p {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.section {
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid var(--color-border);
}

.section:last-child {
	border-bottom: none;
}

.section h4 {
	margin: 0 0 15px 0;
	font-size: 1.1rem;
	font-weight: 500;
}

.section h5 {
	margin: 15px 0 10px 0;
	font-size: 1rem;
	font-weight: 500;
}

.filter-group {
	margin-bottom: 15px;
}

.filter-group label {
	display: block;
	margin-bottom: 5px;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
}

.date-range {
	display: flex;
	gap: 10px;
}

.date-range > * {
	flex: 1;
}

.range-inputs {
	display: flex;
	gap: 10px;
}

.range-inputs > * {
	flex: 1;
}

.loading-small {
	text-align: center;
	padding: 20px;
}

.selection-info {
	color: var(--color-text-maxcontrast);
	margin-bottom: 15px;
}

.bulk-actions {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.stats-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 15px;
}

.stat-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
}

.stat-label {
	font-size: 0.9rem;
	color: var(--color-text-maxcontrast);
}

.stat-value {
	font-weight: 600;
	font-size: 1.1rem;
}

.stat-value.error {
	color: var(--color-error);
}

.stat-value.warning {
	color: var(--color-warning);
}

.stat-value.success {
	color: var(--color-success);
}

.chart-container {
	margin-top: 20px;
}

.performance-chart {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.performance-bar {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 0.85rem;
}

.period-label {
	min-width: 80px;
	font-weight: 500;
}

.performance-progress {
	flex: 1;
	height: 8px;
	background: var(--color-background-dark);
	border-radius: 4px;
	overflow: hidden;
}

.performance-fill {
	height: 100%;
	background: var(--color-success);
	transition: width 0.3s ease;
}

.performance-rate {
	min-width: 40px;
	text-align: right;
	font-weight: 500;
}
</style> 