<script setup>
import { synchronizationStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<div class="viewContainer">
			<!-- Header -->
			<div class="viewHeader">
				<h1 class="viewHeaderTitleIndented">
					{{ t('openconnector', 'Synchronizations') }}
				</h1>
				<p>{{ t('openconnector', 'Manage your data synchronizations and their configurations') }}</p>
			</div>

			<!-- Actions Bar -->
			<div class="viewActionsBar">
				<div class="viewInfo">
					<span class="viewTotalCount">
						{{ t('openconnector', 'Showing {showing} of {total} synchronizations', { showing: paginatedSynchronizations.length, total: filteredSynchronizations.length }) }}
					</span>
					<span v-if="selectedSynchronizations.length > 0" class="viewIndicator">
						({{ t('openconnector', '{count} selected', { count: selectedSynchronizations.length }) }})
					</span>
				</div>
				<div class="viewActions">
					<div class="viewModeSwitchContainer">
						<NcCheckboxRadioSwitch
							v-tooltip="'See synchronizations as cards'"
							:checked="currentViewMode === 'cards'"
							:button-variant="true"
							value="cards"
							name="view_mode_radio"
							type="radio"
							button-variant-grouped="horizontal"
							@click="setViewMode('cards')">
							Cards
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							v-tooltip="'See synchronizations as a table'"
							:checked="currentViewMode === 'table'"
							:button-variant="true"
							value="table"
							name="view_mode_radio"
							type="radio"
							button-variant-grouped="horizontal"
							@click="setViewMode('table')">
							Table
						</NcCheckboxRadioSwitch>
					</div>

					<NcActions
						:force-name="true"
						:inline="4"
						menu-name="Actions">
						<NcActionButton
							:primary="true"
							close-after-click
							@click="synchronizationStore.setSynchronizationItem({}); navigationStore.setModal('editSynchronization')">
							<template #icon>
								<Plus :size="20" />
							</template>
							Add Synchronization
						</NcActionButton>
						<NcActionButton
							close-after-click
							@click="synchronizationStore.refreshSynchronizationList()">
							<template #icon>
								<Refresh :size="20" />
							</template>
							Refresh
						</NcActionButton>
						<NcActionButton
							close-after-click
							@click="navigationStore.setModal('importFile')">
							<template #icon>
								<FileImportOutline :size="20" />
							</template>
							Import
						</NcActionButton>
					</NcActions>
				</div>
			</div>

			<!-- Loading, Error, and Empty States -->
			<NcEmptyContent v-if="synchronizationStore.loading || synchronizationStore.error || !filteredSynchronizations.length"
				:name="emptyContentName"
				:description="emptyContentDescription">
				<template #icon>
					<NcLoadingIcon v-if="synchronizationStore.loading" :size="64" />
					<SyncCircle v-else :size="64" />
				</template>
				<template v-if="!synchronizationStore.loading && !synchronizationStore.error && !synchronizationStore.synchronizationList.length" #action>
					<NcButton type="primary" @click="synchronizationStore.setSynchronizationItem({}); navigationStore.setModal('editSynchronization')">
						{{ t('openconnector', 'Add synchronization') }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<!-- Content -->
			<div v-else>
				<template v-if="currentViewMode === 'cards'">
					<div class="cardGrid">
						<div v-for="synchronization in paginatedSynchronizations" :key="synchronization.id" class="card">
							<div class="cardHeader">
								<h2 v-tooltip.bottom="synchronization.description">
									<VectorPolylinePlus :size="20" />
									{{ synchronization.name }}
								</h2>
								<NcActions :primary="true" menu-name="Actions">
									<template #icon>
										<DotsHorizontal :size="20" />
									</template>
									<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setSelected('synchronizations')">
										<template #icon>
											<Eye :size="20" />
										</template>
										View Details
									</NcActionButton>
									<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setModal('editSynchronization')">
										<template #icon>
											<Pencil :size="20" />
										</template>
										Edit
									</NcActionButton>
									<NcActionButton close-after-click @click="viewContract(synchronization)">
										<template #icon>
											<FileDocumentOutline :size="20" />
										</template>
										View Contract
									</NcActionButton>
									<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setModal('runSynchronization')">
										<template #icon>
											<Play :size="20" />
										</template>
										Run
									</NcActionButton>
									<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setSelected('synchronization-logs')">
										<template #icon>
											<TextBoxOutline :size="20" />
										</template>
										View Logs
									</NcActionButton>
									<NcActionButton close-after-click @click="addSourceConfig(synchronization)">
										<template #icon>
											<DatabaseSettingsOutline :size="20" />
										</template>
										Add Source Config
									</NcActionButton>
									<NcActionButton close-after-click @click="addTargetConfig(synchronization)">
										<template #icon>
											<CardBulletedSettingsOutline :size="20" />
										</template>
										Add Target Config
									</NcActionButton>
									<NcActionButton close-after-click @click="synchronizationStore.exportSynchronization(synchronization.id)">
										<template #icon>
											<FileExportOutline :size="20" />
										</template>
										Export
									</NcActionButton>
									<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setDialog('deleteSynchronization')">
										<template #icon>
											<TrashCanOutline :size="20" />
										</template>
										Delete
									</NcActionButton>
								</NcActions>
							</div>
							<!-- Synchronization Details -->
							<div class="synchronizationDetails">
								<p v-if="synchronization.description" class="synchronizationDescription">
									{{ synchronization.description }}
								</p>
								<!-- Synchronization Statistics Table -->
								<table class="statisticsTable synchronizationStats">
									<thead>
										<tr>
											<th>{{ t('openconnector', 'Property') }}</th>
											<th>{{ t('openconnector', 'Value') }}</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>{{ t('openconnector', 'Source Type') }}</td>
											<td>{{ synchronization.sourceType || 'Unknown' }}</td>
										</tr>
										<tr>
											<td>{{ t('openconnector', 'Target Type') }}</td>
											<td>{{ synchronization.targetType || 'Unknown' }}</td>
										</tr>
										<tr v-if="synchronization.version">
											<td>{{ t('openconnector', 'Version') }}</td>
											<td>{{ synchronization.version }}</td>
										</tr>
										<tr>
											<td>{{ t('openconnector', 'Source Configs') }}</td>
											<td>{{ getSourceConfigCount(synchronization) }}</td>
										</tr>
										<tr>
											<td>{{ t('openconnector', 'Target Configs') }}</td>
											<td>{{ getTargetConfigCount(synchronization) }}</td>
										</tr>
										<tr v-if="synchronization.sourceLastSynced">
											<td>{{ t('openconnector', 'Source Last Synced') }}</td>
											<td>{{ new Date(synchronization.sourceLastSynced).toLocaleDateString() + ', ' + new Date(synchronization.sourceLastSynced).toLocaleTimeString() }}</td>
										</tr>
										<tr v-if="synchronization.targetLastSynced">
											<td>{{ t('openconnector', 'Target Last Synced') }}</td>
											<td>{{ new Date(synchronization.targetLastSynced).toLocaleDateString() + ', ' + new Date(synchronization.targetLastSynced).toLocaleTimeString() }}</td>
										</tr>
										<tr>
											<td>{{ t('openconnector', 'Created') }}</td>
											<td>{{ synchronization.created ? new Date(synchronization.created).toLocaleDateString() : '-' }}</td>
										</tr>
										<tr>
											<td>{{ t('openconnector', 'Updated') }}</td>
											<td>{{ synchronization.updated ? new Date(synchronization.updated).toLocaleDateString() : '-' }}</td>
										</tr>
									</tbody>
								</table>
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
									<th>{{ t('openconnector', 'Name') }}</th>
									<th>{{ t('openconnector', 'Source Type') }}</th>
									<th>{{ t('openconnector', 'Target Type') }}</th>
									<th>{{ t('openconnector', 'Version') }}</th>
									<th>{{ t('openconnector', 'Configs') }}</th>
									<th>{{ t('openconnector', 'Last Synced') }}</th>
									<th>{{ t('openconnector', 'Updated') }}</th>
									<th class="tableColumnActions">
										{{ t('openconnector', 'Actions') }}
									</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="synchronization in paginatedSynchronizations"
									:key="synchronization.id"
									class="viewTableRow"
									:class="{ viewTableRowSelected: selectedSynchronizations.includes(synchronization.id) }">
									<td class="tableColumnCheckbox">
										<NcCheckboxRadioSwitch
											:checked="selectedSynchronizations.includes(synchronization.id)"
											@update:checked="(checked) => toggleSynchronizationSelection(synchronization.id, checked)" />
									</td>
									<td class="tableColumnTitle">
										<div class="titleContent">
											<strong>{{ synchronization.name }}</strong>
											<span v-if="synchronization.description" class="textDescription textEllipsis">{{ synchronization.description }}</span>
										</div>
									</td>
									<td>{{ synchronization.sourceType || 'Unknown' }}</td>
									<td>{{ synchronization.targetType || 'Unknown' }}</td>
									<td>{{ synchronization.version || '-' }}</td>
									<td>{{ getSourceConfigCount(synchronization) + getTargetConfigCount(synchronization) }}</td>
									<td>{{ getLastSyncedDisplay(synchronization) }}</td>
									<td>{{ synchronization.updated ? new Date(synchronization.updated).toLocaleDateString() + ', ' + new Date(synchronization.updated).toLocaleTimeString() : '-' }}</td>
									<td class="tableColumnActions">
										<NcActions :primary="false">
											<template #icon>
												<DotsHorizontal :size="20" />
											</template>
											<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setSelected('synchronizations')">
												<template #icon>
													<Eye :size="20" />
												</template>
												View Details
											</NcActionButton>
											<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setModal('editSynchronization')">
												<template #icon>
													<Pencil :size="20" />
												</template>
												Edit
											</NcActionButton>
											<NcActionButton close-after-click @click="viewContract(synchronization)">
												<template #icon>
													<FileDocumentOutline :size="20" />
												</template>
												View Contract
											</NcActionButton>
											<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setModal('runSynchronization')">
												<template #icon>
													<Play :size="20" />
												</template>
												Run
											</NcActionButton>
											<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setSelected('synchronization-logs')">
												<template #icon>
													<TextBoxOutline :size="20" />
												</template>
												View Logs
											</NcActionButton>
											<NcActionButton close-after-click @click="addSourceConfig(synchronization)">
												<template #icon>
													<DatabaseSettingsOutline :size="20" />
												</template>
												Add Source Config
											</NcActionButton>
											<NcActionButton close-after-click @click="addTargetConfig(synchronization)">
												<template #icon>
													<CardBulletedSettingsOutline :size="20" />
												</template>
												Add Target Config
											</NcActionButton>
											<NcActionButton close-after-click @click="synchronizationStore.exportSynchronization(synchronization.id)">
												<template #icon>
													<FileExportOutline :size="20" />
												</template>
												Export
											</NcActionButton>
											<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setDialog('deleteSynchronization')">
												<template #icon>
													<TrashCanOutline :size="20" />
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
				v-if="filteredSynchronizations.length > 0"
				:current-page="pagination.page || 1"
				:total-pages="Math.ceil(filteredSynchronizations.length / (pagination.limit || 20))"
				:total-items="filteredSynchronizations.length"
				:current-page-size="pagination.limit || 20"
				:min-items-to-show="0"
				@page-changed="onPageChanged"
				@page-size-changed="onPageSizeChanged" />
		</div>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcLoadingIcon, NcActions, NcActionButton, NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import SyncCircle from 'vue-material-design-icons/SyncCircle.vue'
import VectorPolylinePlus from 'vue-material-design-icons/VectorPolylinePlus.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import Sync from 'vue-material-design-icons/Sync.vue'
import Play from 'vue-material-design-icons/Play.vue'
import TextBoxOutline from 'vue-material-design-icons/TextBoxOutline.vue'
import DatabaseSettingsOutline from 'vue-material-design-icons/DatabaseSettingsOutline.vue'
import CardBulletedSettingsOutline from 'vue-material-design-icons/CardBulletedSettingsOutline.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import FileExportOutline from 'vue-material-design-icons/FileExportOutline.vue'
import FileImportOutline from 'vue-material-design-icons/FileImportOutline.vue'

import PaginationComponent from '../../components/PaginationComponent.vue'
import { synchronizationStore, navigationStore } from '../../store/store.js'

export default {
	name: 'SynchronizationsIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		NcActions,
		NcActionButton,
		NcCheckboxRadioSwitch,
		NcButton,
		SyncCircle,
		VectorPolylinePlus,
		DotsHorizontal,
		Pencil,
		TrashCanOutline,
		Refresh,
		Plus,
		Eye,
		Sync,
		Play,
		TextBoxOutline,
		DatabaseSettingsOutline,
		CardBulletedSettingsOutline,
		FileDocumentOutline,
		FileExportOutline,
		FileImportOutline,
		PaginationComponent,
	},
	data() {
		return {
			synchronizationStore,
			navigationStore,
			selectedSynchronizations: [],
			pagination: {
				page: 1,
				limit: 20,
			},
		}
	},
	computed: {
		currentViewMode() {
			return this.synchronizationStore.viewMode
		},
		filteredSynchronizations() {
			if (!this.synchronizationStore.synchronizationList) return []
			return this.synchronizationStore.synchronizationList
		},
		paginatedSynchronizations() {
			const start = ((this.pagination.page || 1) - 1) * (this.pagination.limit || 20)
			const end = start + (this.pagination.limit || 20)
			return this.filteredSynchronizations.slice(start, end)
		},
		allSelected() {
			return this.filteredSynchronizations.length > 0 && this.filteredSynchronizations.every(sync => this.selectedSynchronizations.includes(sync.id))
		},
		someSelected() {
			return this.selectedSynchronizations.length > 0 && !this.allSelected
		},
		emptyContentName() {
			if (this.synchronizationStore.loading) {
				return t('openconnector', 'Loading synchronizations...')
			} else if (this.synchronizationStore.error) {
				return this.synchronizationStore.error
			} else if (!this.synchronizationStore.synchronizationList?.length) {
				return t('openconnector', 'No synchronizations found')
			}
			return ''
		},
		emptyContentDescription() {
			if (this.synchronizationStore.loading) {
				return t('openconnector', 'Please wait while we fetch your synchronizations.')
			} else if (this.synchronizationStore.error) {
				return t('openconnector', 'Please try again later.')
			} else if (!this.synchronizationStore.synchronizationList?.length) {
				return t('openconnector', 'No synchronizations are available.')
			}
			return ''
		},
	},
	mounted() {
		this.synchronizationStore.refreshSynchronizationList()
	},
	methods: {
		setViewMode(mode) {
			if (mode === 'cards' || mode === 'table') {
				this.synchronizationStore.setViewMode(mode)
			}
		},
		toggleSelectAll(checked) {
			if (checked) {
				this.selectedSynchronizations = this.filteredSynchronizations.map(sync => sync.id)
			} else {
				this.selectedSynchronizations = []
			}
		},
		toggleSynchronizationSelection(syncId, checked) {
			if (checked) {
				this.selectedSynchronizations.push(syncId)
			} else {
				this.selectedSynchronizations = this.selectedSynchronizations.filter(id => id !== syncId)
			}
		},
		onPageChanged(page) {
			this.pagination.page = page
		},
		onPageSizeChanged(pageSize) {
			this.pagination.page = 1
			this.pagination.limit = pageSize
		},
		getSourceConfigCount(synchronization) {
			const config = synchronization.sourceConfig || {}
			return Object.keys(config).length
		},
		getTargetConfigCount(synchronization) {
			const config = synchronization.targetConfig || {}
			return Object.keys(config).length
		},
		getLastSyncedDisplay(synchronization) {
			const sourceSynced = synchronization.sourceLastSynced
			const targetSynced = synchronization.targetLastSynced
			
			if (sourceSynced && targetSynced) {
				const sourceDate = new Date(sourceSynced)
				const targetDate = new Date(targetSynced)
				const latestDate = sourceDate > targetDate ? sourceDate : targetDate
				return latestDate.toLocaleDateString() + ', ' + latestDate.toLocaleTimeString()
			} else if (sourceSynced) {
				return new Date(sourceSynced).toLocaleDateString() + ', ' + new Date(sourceSynced).toLocaleTimeString()
			} else if (targetSynced) {
				return new Date(targetSynced).toLocaleDateString() + ', ' + new Date(targetSynced).toLocaleTimeString()
			}
			return '-'
		},
		addSourceConfig(synchronization) {
			this.synchronizationStore.setSynchronizationItem(synchronization)
			this.synchronizationStore.setSynchronizationSourceConfigKey(null)
			this.navigationStore.setModal('editSynchronizationSourceConfig')
		},
		addTargetConfig(synchronization) {
			this.synchronizationStore.setSynchronizationItem(synchronization)
			this.synchronizationStore.setSynchronizationTargetConfigKey(null)
			this.navigationStore.setModal('editSynchronizationTargetConfig')
		},
		/**
		 * Navigate to the contract view for a specific synchronization
		 * @param {object} synchronization - The synchronization to view contract for
		 */
		viewContract(synchronization) {
			// Set the selected synchronization item
			this.synchronizationStore.setSynchronizationItem(synchronization)
			// Navigate to the contracts view
			this.navigationStore.setSelected('contracts')
		},
	},
}
</script>
