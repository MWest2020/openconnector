<script setup>
import { contractStore, synchronizationStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<div class="container">
			<!-- Header -->
			<div class="header">
				<h1>{{ t('openconnector', 'Synchronization Contracts') }}</h1>
				<p>{{ t('openconnector', 'Manage and monitor synchronization contracts') }}</p>
			</div>

			<!-- Actions Bar -->
			<div v-if="selectedItems.length > 0" class="selection-header">
				<h3 class="selection-title">
					{{ t('openconnector', '{count} contracts selected', { count: selectedItems.length }) }}
				</h3>
			</div>

			<div class="actions-bar">
				<div class="actions">
					<NcButton
						v-if="selectedItems.length > 0"
						type="primary"
						@click="bulkActivate">
						<template #icon>
							<Play :size="20" />
						</template>
						{{ t('openconnector', 'Activate Selected') }}
					</NcButton>
					<NcButton
						v-if="selectedItems.length > 0"
						type="error"
						@click="bulkDeactivate">
						<template #icon>
							<Pause :size="20" />
						</template>
						{{ t('openconnector', 'Deactivate Selected') }}
					</NcButton>
					<NcButton @click="refreshItems">
						<template #icon>
							<Refresh :size="20" />
						</template>
						{{ t('openconnector', 'Refresh') }}
					</NcButton>
				</div>
			</div>

			<!-- Contracts Table -->
			<div v-if="contractStore.contractsLoading" class="loading">
				<NcLoadingIcon :size="64" />
				<p>{{ t('openconnector', 'Loading contracts...') }}</p>
			</div>

			<NcEmptyContent v-else-if="!filteredItems.length"
				:name="t('openconnector', 'No contracts found')"
				:description="t('openconnector', 'There are no contracts matching your current filters.')">
				<template #icon>
					<FileDocumentOutline />
				</template>
			</NcEmptyContent>

			<div v-else class="table-container">
				<table class="items-table">
					<thead>
						<tr>
							<th class="checkbox-column">
								<NcCheckboxRadioSwitch
									:checked="allSelected"
									:indeterminate="someSelected"
									@update:checked="toggleSelectAll" />
							</th>
							<th>{{ t('openconnector', 'Name') }}</th>
							<th>{{ t('openconnector', 'Synchronization') }}</th>
							<th>{{ t('openconnector', 'Status') }}</th>
							<th>{{ t('openconnector', 'Last Executed') }}</th>
							<th>{{ t('openconnector', 'Next Execution') }}</th>
							<th>{{ t('openconnector', 'Success Rate') }}</th>
							<th>{{ t('openconnector', 'Actions') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="item in paginatedItems"
							:key="item.id"
							class="item-row"
							:class="{ selected: selectedItems.includes(item.id) }">
							<td class="checkbox-column">
								<NcCheckboxRadioSwitch
									:checked="selectedItems.includes(item.id)"
									@update:checked="(checked) => toggleItemSelection(item.id, checked)" />
							</td>
							<td class="title-column">
								<div class="title-content">
									<strong>{{ getContractName(item) }}</strong>
									<span v-if="item.description" class="description">{{ item.description }}</span>
								</div>
							</td>
							<td>{{ getSynchronizationName(item.synchronizationId) }}</td>
							<td>
								<span :class="getStatusType(item.getStatus ? item.getStatus() : 'unknown')">
									{{ getStatusLabel(item.getStatus ? item.getStatus() : 'unknown') }}
								</span>
							</td>
							<td>
								<NcDateTime v-if="item.lastExecuted" :timestamp="new Date(item.lastExecuted)" :ignore-seconds="true" />
								<span v-else>{{ t('openconnector', 'Never') }}</span>
							</td>
							<td>
								<NcDateTime v-if="item.nextExecution" :timestamp="new Date(item.nextExecution)" :ignore-seconds="true" />
								<span v-else>{{ t('openconnector', 'Not scheduled') }}</span>
							</td>
							<td>
								<div class="success-rate">
									<span>{{ calculateSuccessRate(item) }}%</span>
									<div class="rate-bar">
										<div class="rate-fill" :style="{ width: calculateSuccessRate(item) + '%' }"></div>
									</div>
								</div>
							</td>
							<td class="actions-column">
								<NcActions>
									<NcActionButton @click="toggleContractStatus(item)">
										<template #icon>
											<Play v-if="item.getStatus && item.getStatus() === 'inactive'" :size="20" />
											<Pause v-else :size="20" />
										</template>
										{{ (item.getStatus && item.getStatus() === 'inactive') ? t('openconnector', 'Activate') : t('openconnector', 'Deactivate') }}
									</NcActionButton>
									<NcActionButton @click="executeContract(item)">
										<template #icon>
											<PlayCircle :size="20" />
										</template>
										{{ t('openconnector', 'Execute Now') }}
									</NcActionButton>
									<NcActionButton @click="viewLogs(item)">
										<template #icon>
											<TextBoxOutline :size="20" />
										</template>
										{{ t('openconnector', 'View Logs') }}
									</NcActionButton>
								</NcActions>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Pagination -->
			<div v-if="totalPages > 1" class="pagination">
				<NcButton
					:disabled="currentPage === 1"
					@click="changePage(1)">
					{{ t('openconnector', 'First') }}
				</NcButton>
				<NcButton
					:disabled="currentPage === 1"
					@click="changePage(currentPage - 1)">
					{{ t('openconnector', 'Previous') }}
				</NcButton>
				<span class="page-info">
					{{ t('openconnector', 'Page {current} of {total}', { current: currentPage, total: totalPages }) }}
				</span>
				<NcButton
					:disabled="currentPage === totalPages"
					@click="changePage(currentPage + 1)">
					{{ t('openconnector', 'Next') }}
				</NcButton>
				<NcButton
					:disabled="currentPage === totalPages"
					@click="changePage(totalPages)">
					{{ t('openconnector', 'Last') }}
				</NcButton>
			</div>
		</div>
	</NcAppContent>
</template>

<script>
import {
	NcAppContent,
	NcEmptyContent,
	NcButton,
	NcLoadingIcon,
	NcCheckboxRadioSwitch,
	NcActions,
	NcActionButton,
	NcDateTime,
} from '@nextcloud/vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import Play from 'vue-material-design-icons/Play.vue'
import Pause from 'vue-material-design-icons/Pause.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import PlayCircle from 'vue-material-design-icons/PlayCircle.vue'
import TextBoxOutline from 'vue-material-design-icons/TextBoxOutline.vue'

export default {
	name: 'ContractsIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		NcLoadingIcon,
		NcCheckboxRadioSwitch,
		NcActions,
		NcActionButton,
		NcDateTime,
		FileDocumentOutline,
		Play,
		Pause,
		Refresh,
		PlayCircle,
		TextBoxOutline,
	},
	data() {
		return {
			selectedItems: [],
		}
	},
	computed: {
		/**
		 * Get filtered contracts from store
		 * @return {Array} Array of filtered contracts
		 */
		filteredItems() {
			return contractStore.contractsList || []
		},
		/**
		 * Get paginated contracts from store
		 * @return {Array} Array of paginated contracts
		 */
		paginatedItems() {
			return this.filteredItems
		},
		/**
		 * Get total pages from store
		 * @return {number} Total number of pages
		 */
		totalPages() {
			return contractStore.contractsPagination.pages || 1
		},
		/**
		 * Get current page from store
		 * @return {number} Current page number
		 */
		currentPage() {
			return contractStore.contractsPagination.page || 1
		},
		/**
		 * Check if all items are selected
		 * @return {boolean} Whether all items are selected
		 */
		allSelected() {
			return this.paginatedItems.length > 0 && this.paginatedItems.every(item => this.selectedItems.includes(item.id))
		},
		/**
		 * Check if some items are selected
		 * @return {boolean} Whether some items are selected
		 */
		someSelected() {
			return this.selectedItems.length > 0 && !this.allSelected
		},
	},
	watch: {
		selectedItems() {
			this.updateCounts()
		},
		filteredItems() {
			this.updateCounts()
		},
	},
	async mounted() {
		// Load initial data
		await this.loadItems()

		// Update counts
		this.updateCounts()

		// Listen for filter changes from sidebar
		this.$root.$on('contracts-filters-changed', this.handleFiltersChanged)
		this.$root.$on('contracts-bulk-activate', this.bulkActivate)
		this.$root.$on('contracts-bulk-deactivate', this.bulkDeactivate)
		this.$root.$on('contracts-export-filtered', this.exportFiltered)
	},
	beforeDestroy() {
		this.$root.$off('contracts-filters-changed')
		this.$root.$off('contracts-bulk-activate')
		this.$root.$off('contracts-bulk-deactivate')
		this.$root.$off('contracts-export-filtered')
	},
	methods: {
		/**
		 * Load contracts from store
		 * @return {Promise<void>}
		 */
		async loadItems() {
			try {
				await contractStore.fetchContracts()

				// Load synchronization data if not already loaded
				if (!synchronizationStore.synchronizationList.length) {
					await synchronizationStore.refreshSynchronizationList()
				}
			} catch (error) {
				console.error('Error loading contracts:', error)
			}
		},
		/**
		 * Handle filter changes from sidebar
		 * @param {object} filters - Filter object from sidebar
		 * @return {void}
		 */
		async handleFiltersChanged(filters) {
			contractStore.setContractsFilters(filters)

			// Reset pagination and fetch new data
			try {
				await contractStore.fetchContracts({
					page: 1,
					filters: filters,
				})

				// Clear selection when filters change
				this.selectedItems = []
			} catch (error) {
				console.error('Error applying filters:', error)
			}
		},
		/**
		 * Get contract name
		 * @param {object} contract - The contract object
		 * @return {string} The contract name
		 */
		getContractName(contract) {
			return contract.getDisplayName ? contract.getDisplayName() : `Contract ${contract.id}`
		},
		/**
		 * Get synchronization name by ID
		 * @param {string|number} synchronizationId - The synchronization ID
		 * @return {string} The synchronization name
		 */
		getSynchronizationName(synchronizationId) {
			if (!synchronizationId) return t('openconnector', 'Unknown Synchronization')

			const synchronization = synchronizationStore.synchronizationList.find(s => s.id === parseInt(synchronizationId))
			return synchronization?.name || `Synchronization ${synchronizationId}`
		},
		/**
		 * Get status badge type
		 * @param {string} status - Contract status
		 * @return {string} Badge type
		 */
		getStatusType(status) {
			switch (status) {
				case 'active':
					return 'success'
				case 'inactive':
					return 'secondary'
				case 'error':
					return 'error'
				default:
					return 'secondary'
			}
		},
		/**
		 * Get status label
		 * @param {string} status - Contract status
		 * @return {string} Status label
		 */
		getStatusLabel(status) {
			switch (status) {
				case 'active':
					return t('openconnector', 'Active')
				case 'inactive':
					return t('openconnector', 'Inactive')
				case 'error':
					return t('openconnector', 'Error')
				default:
					return t('openconnector', 'Unknown')
			}
		},
		/**
		 * Calculate success rate for a contract
		 * @param {object} contract - The contract object
		 * @return {number} Success rate percentage
		 */
		calculateSuccessRate(contract) {
			if (!contract.totalExecutions || contract.totalExecutions === 0) {
				return 0
			}
			return Math.round((contract.successfulExecutions / contract.totalExecutions) * 100)
		},
		/**
		 * Toggle selection for all items on current page
		 * @param {boolean} checked - Whether to select or deselect all
		 * @return {void}
		 */
		toggleSelectAll(checked) {
			if (checked) {
				this.paginatedItems.forEach(item => {
					if (!this.selectedItems.includes(item.id)) {
						this.selectedItems.push(item.id)
					}
				})
			} else {
				this.paginatedItems.forEach(item => {
					const index = this.selectedItems.indexOf(item.id)
					if (index > -1) {
						this.selectedItems.splice(index, 1)
					}
				})
			}
		},
		/**
		 * Toggle selection for individual item
		 * @param {string} itemId - ID of the item to toggle
		 * @param {boolean} checked - Whether to select or deselect
		 * @return {void}
		 */
		toggleItemSelection(itemId, checked) {
			if (checked) {
				if (!this.selectedItems.includes(itemId)) {
					this.selectedItems.push(itemId)
				}
			} else {
				const index = this.selectedItems.indexOf(itemId)
				if (index > -1) {
					this.selectedItems.splice(index, 1)
				}
			}
		},
		/**
		 * Bulk activate selected contracts
		 * @return {Promise<void>}
		 */
		async bulkActivate() {
			if (this.selectedItems.length === 0) return

			try {
				await contractStore.activateMultiple(this.selectedItems)
				this.selectedItems = []
				// Refresh the list
				await this.loadItems()
			} catch (error) {
				console.error('Error activating contracts:', error)
			}
		},
		/**
		 * Bulk deactivate selected contracts
		 * @return {Promise<void>}
		 */
		async bulkDeactivate() {
			if (this.selectedItems.length === 0) return

			try {
				await contractStore.deactivateMultiple(this.selectedItems)
				this.selectedItems = []
				// Refresh the list
				await this.loadItems()
			} catch (error) {
				console.error('Error deactivating contracts:', error)
			}
		},
		/**
		 * Toggle contract status
		 * @param {object} contract - Contract to toggle
		 * @return {Promise<void>}
		 */
		async toggleContractStatus(contract) {
			try {
				const currentStatus = contract.getStatus ? contract.getStatus() : 'inactive'
				if (currentStatus === 'active') {
					await contractStore.deactivateContract(contract.id)
				} else {
					await contractStore.activateContract(contract.id)
				}
				// Refresh the list
				await this.loadItems()
			} catch (error) {
				console.error('Error toggling contract status:', error)
			}
		},
		/**
		 * Execute contract immediately
		 * @param {object} contract - Contract to execute
		 * @return {Promise<void>}
		 */
		async executeContract(contract) {
			try {
				await contractStore.executeContract(contract.id)
				// Refresh the list
				await this.loadItems()
			} catch (error) {
				console.error('Error executing contract:', error)
			}
		},
		/**
		 * View logs for contract
		 * @param {object} contract - Contract to view logs for
		 * @return {void}
		 */
		viewLogs(contract) {
			// Navigate to logs view with contract filter
			navigationStore.setSelected('logs')
			this.$root.$emit('logs-filter-by-contract', contract.id)
		},
		/**
		 * Change page
		 * @param {number} page - The page number to change to
		 * @return {Promise<void>}
		 */
		async changePage(page) {
			try {
				await contractStore.fetchContracts({ page })
				// Clear selection when page changes
				this.selectedItems = []
			} catch (error) {
				// Handle error silently
			}
		},
		/**
		 * Refresh contracts list
		 * @return {Promise<void>}
		 */
		async refreshItems() {
			await this.loadItems()
			this.selectedItems = []
		},
		/**
		 * Update counts for sidebar
		 * @return {void}
		 */
		updateCounts() {
			this.$root.$emit('contracts-selection-count', this.selectedItems.length)
			this.$root.$emit('contracts-filtered-count', this.filteredItems.length)
		},
	},
}
</script>

<style scoped>
.container {
	padding: 20px;
	max-width: 100%;
}

.header {
	margin-bottom: 30px;
}

.header h1 {
	margin: 0 0 10px 0;
	font-size: 2rem;
	font-weight: 300;
}

.header p {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.selection-header {
	margin-bottom: 20px;
	padding: 10px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
}

.selection-title {
	margin: 0;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
}

.actions-bar {
	display: flex;
	align-items: center;
	margin-bottom: 20px;
	padding: 10px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
}

.actions {
	display: flex;
	align-items: center;
	gap: 15px;
	margin-left: auto;
}

.loading {
	text-align: center;
	padding: 50px;
}

.loading p {
	margin-top: 20px;
	color: var(--color-text-maxcontrast);
}

.table-container {
	background: var(--color-main-background);
	border-radius: var(--border-radius);
	overflow: hidden;
	box-shadow: 0 2px 4px var(--color-box-shadow);
}

.items-table {
	width: 100%;
	border-collapse: collapse;
}

.items-table th,
.items-table td {
	padding: 12px;
	text-align: left;
	border-bottom: 1px solid var(--color-border);
}

.items-table th {
	background: var(--color-background-hover);
	font-weight: 500;
	color: var(--color-text-maxcontrast);
}

.checkbox-column {
	width: 50px;
	text-align: center;
}

.title-column {
	min-width: 200px;
	max-width: 250px;
}

.title-content {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.description {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}

.actions-column {
	width: 120px;
	text-align: center;
}

.item-row:hover {
	background: var(--color-background-hover);
}

.item-row.selected {
	background: var(--color-primary-light);
}

.success-rate {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
}

.rate-bar {
	width: 60px;
	height: 8px;
	background: var(--color-background-dark);
	border-radius: 4px;
	overflow: hidden;
}

.rate-fill {
	height: 100%;
	background: var(--color-success);
	transition: width 0.3s ease;
}

.pagination {
	display: flex;
	justify-content: center;
	align-items: center;
	gap: 20px;
	margin-top: 30px;
	padding: 20px;
}

.page-info {
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

/* Responsive table adjustments */
@media (max-width: 1200px) {
	.title-column {
		min-width: 150px;
		max-width: 200px;
	}
}

/* Status badge styles */
.success {
	display: inline-block;
	padding: 4px 8px;
	background: var(--color-success);
	color: white;
	border-radius: 12px;
	font-size: 0.75rem;
	font-weight: 500;
}

.secondary {
	display: inline-block;
	padding: 4px 8px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	border-radius: 12px;
	font-size: 0.75rem;
	font-weight: 500;
}

.error {
	display: inline-block;
	padding: 4px 8px;
	background: var(--color-error);
	color: white;
	border-radius: 12px;
	font-size: 0.75rem;
	font-weight: 500;
}
</style> 