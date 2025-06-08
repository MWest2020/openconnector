<script setup>
import { sourceStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<div class="viewContainer">
			<!-- Header -->
			<div class="viewHeader">
				<h1 class="viewHeaderTitleIndented">
					{{ t('openconnector', 'Sources') }}
				</h1>
				<p>{{ t('openconnector', 'Manage your data sources and their configurations') }}</p>
			</div>

			<!-- Actions Bar -->
			<div class="viewActionsBar">
				<div class="viewInfo">
					<span class="viewTotalCount">
						{{ t('openconnector', 'Showing {showing} of {total} sources', { showing: paginatedSources.length, total: filteredSources.length }) }}
					</span>
					<span v-if="selectedSources.length > 0" class="viewIndicator">
						({{ t('openconnector', '{count} selected', { count: selectedSources.length }) }})
					</span>
				</div>
				<div class="viewActions">
					<div class="viewModeSwitchContainer">
						<NcCheckboxRadioSwitch
							v-model="sourceStore.viewMode"
							v-tooltip="'See sources as cards'"
							:button-variant="true"
							value="cards"
							name="view_mode_radio"
							type="radio"
							button-variant-grouped="horizontal">
							Cards
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							v-model="sourceStore.viewMode"
							v-tooltip="'See sources as a table'"
							:button-variant="true"
							value="table"
							name="view_mode_radio"
							type="radio"
							button-variant-grouped="horizontal">
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
							@click="sourceStore.setSourceItem({}); navigationStore.setModal('editSource')">
							<template #icon>
								<Plus :size="20" />
							</template>
							Add Source
						</NcActionButton>
						<NcActionButton
							close-after-click
							@click="navigationStore.setModal('importFile')">
							<template #icon>
								<FileImportOutline :size="20" />
							</template>
							Import
						</NcActionButton>
						<NcActionButton
							close-after-click
							@click="sourceStore.refreshSourceList()">
							<template #icon>
								<Refresh :size="20" />
							</template>
							Refresh
						</NcActionButton>
					</NcActions>
				</div>
			</div>

			<!-- Loading, Error, and Empty States -->
			<NcEmptyContent v-if="sourceStore.loading || sourceStore.error || !filteredSources.length"
				:name="emptyContentName"
				:description="emptyContentDescription">
				<template #icon>
					<NcLoadingIcon v-if="sourceStore.loading" :size="64" />
					<DatabaseArrowLeftOutline v-else :size="64" />
				</template>
				<template v-if="!sourceStore.loading && !sourceStore.error && !sourceStore.sourceList.length" #action>
					<NcButton type="primary" @click="sourceStore.setSourceItem({}); navigationStore.setModal('editSource')">
						{{ t('openconnector', 'Add source') }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<!-- Content -->
			<div v-else>
				<template v-if="sourceStore.viewMode === 'cards'">
					<div class="cardGrid">
						<div v-for="source in paginatedSources" :key="source.id" class="card">
							<div class="cardHeader">
								<h2 v-tooltip.bottom="source.description">
									<DatabaseArrowLeftOutline :size="20" />
									{{ source.name }}
								</h2>
								<NcActions :primary="true" menu-name="Actions">
									<template #icon>
										<DotsHorizontal :size="20" />
									</template>
									<NcActionButton close-after-click @click="sourceStore.setSourceItem(source); navigationStore.setModal('viewSource')">
										<template #icon>
											<Eye :size="20" />
										</template>
										View
									</NcActionButton>
									<NcActionButton close-after-click @click="sourceStore.setSourceItem(source); navigationStore.setModal('editSource')">
										<template #icon>
											<Pencil :size="20" />
										</template>
										Edit
									</NcActionButton>
									<NcActionButton close-after-click @click="sourceStore.setSourceItem(source); navigationStore.setModal('testSource')">
										<template #icon>
											<Sync :size="20" />
										</template>
										Test
									</NcActionButton>
									<NcActionButton close-after-click @click="addSourceConfiguration(source)">
										<template #icon>
											<Plus :size="20" />
										</template>
										Add Configuration
									</NcActionButton>
									<NcActionButton close-after-click @click="addSourceAuthentication(source)">
										<template #icon>
											<Plus :size="20" />
										</template>
										Add Authentication
									</NcActionButton>
									<NcActionButton close-after-click @click="sourceStore.exportSource(source.id)">
										<template #icon>
											<FileExportOutline :size="20" />
										</template>
										Export
									</NcActionButton>
									<NcActionButton close-after-click @click="sourceStore.setSourceItem(source); navigationStore.setDialog('deleteSource')">
										<template #icon>
											<TrashCanOutline :size="20" />
										</template>
										Delete
									</NcActionButton>
								</NcActions>
							</div>
							<!-- Source Details -->
							<div class="sourceDetails">
								<p v-if="source.description" class="sourceDescription">
									{{ source.description }}
								</p>
								<!-- Source Statistics Table -->
								<table class="statisticsTable sourceStats">
									<thead>
										<tr>
											<th>{{ t('openconnector', 'Property') }}</th>
											<th>{{ t('openconnector', 'Value') }}</th>
											<th>{{ t('openconnector', 'Status') }}</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>{{ t('openconnector', 'Type') }}</td>
											<td>{{ source.type || 'Unknown' }}</td>
											<td>{{ source.type ? 'Configured' : 'Not Set' }}</td>
										</tr>
										<tr v-if="source.location">
											<td>{{ t('openconnector', 'Location') }}</td>
											<td class="truncatedUrl">
												{{ source.location }}
											</td>
											<td>{{ 'Connected' }}</td>
										</tr>
										<tr v-if="source.version">
											<td>{{ t('openconnector', 'Version') }}</td>
											<td>{{ source.version }}</td>
											<td>{{ 'Available' }}</td>
										</tr>
										<tr>
											<td>{{ t('openconnector', 'Configurations') }}</td>
											<td>{{ getConfigurationCount(source) }}</td>
											<td>{{ getConfigurationCount(source) > 0 ? 'Configured' : 'Empty' }}</td>
										</tr>
										<tr>
											<td>{{ t('openconnector', 'Authentication') }}</td>
											<td>{{ getAuthenticationCount(source) }}</td>
											<td>{{ getAuthenticationCount(source) > 0 ? 'Secured' : 'Open' }}</td>
										</tr>
										<tr>
											<td>{{ t('openconnector', 'Created') }}</td>
											<td>{{ source.created ? new Date(source.created).toLocaleDateString() : '-' }}</td>
											<td>-</td>
										</tr>
										<tr>
											<td>{{ t('openconnector', 'Updated') }}</td>
											<td>{{ source.updated ? new Date(source.updated).toLocaleDateString() : '-' }}</td>
											<td>-</td>
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
									<th>{{ t('openconnector', 'Type') }}</th>
									<th>{{ t('openconnector', 'Location') }}</th>
									<th>{{ t('openconnector', 'Version') }}</th>
									<th>{{ t('openconnector', 'Configurations') }}</th>
									<th>{{ t('openconnector', 'Created') }}</th>
									<th>{{ t('openconnector', 'Updated') }}</th>
									<th class="tableColumnActions">
										{{ t('openconnector', 'Actions') }}
									</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="source in paginatedSources"
									:key="source.id"
									class="viewTableRow"
									:class="{ viewTableRowSelected: selectedSources.includes(source.id) }">
									<td class="tableColumnCheckbox">
										<NcCheckboxRadioSwitch
											:checked="selectedSources.includes(source.id)"
											@update:checked="(checked) => toggleSourceSelection(source.id, checked)" />
									</td>
									<td class="tableColumnTitle">
										<div class="titleContent">
											<strong>{{ source.name }}</strong>
											<span v-if="source.description" class="textDescription textEllipsis">{{ source.description }}</span>
										</div>
									</td>
									<td>{{ source.type || 'Unknown' }}</td>
									<td class="tableColumnConstrained">
										<span v-if="source.location" class="truncatedUrl">{{ source.location }}</span>
										<span v-else>-</span>
									</td>
									<td>{{ source.version || '-' }}</td>
									<td>{{ getConfigurationCount(source) }}</td>
									<td>{{ source.created ? new Date(source.created).toLocaleDateString() + ', ' + new Date(source.created).toLocaleTimeString() : '-' }}</td>
									<td>{{ source.updated ? new Date(source.updated).toLocaleDateString() + ', ' + new Date(source.updated).toLocaleTimeString() : '-' }}</td>
									<td class="tableColumnActions">
										<NcActions :primary="false">
											<template #icon>
												<DotsHorizontal :size="20" />
											</template>
											<NcActionButton close-after-click @click="sourceStore.setSourceItem(source); navigationStore.setModal('viewSource')">
												<template #icon>
													<Eye :size="20" />
												</template>
												View
											</NcActionButton>
											<NcActionButton close-after-click @click="sourceStore.setSourceItem(source); navigationStore.setModal('editSource')">
												<template #icon>
													<Pencil :size="20" />
												</template>
												Edit
											</NcActionButton>
											<NcActionButton close-after-click @click="sourceStore.setSourceItem(source); navigationStore.setModal('testSource')">
												<template #icon>
													<Sync :size="20" />
												</template>
												Test
											</NcActionButton>
											<NcActionButton close-after-click @click="addSourceConfiguration(source)">
												<template #icon>
													<Plus :size="20" />
												</template>
												Add Configuration
											</NcActionButton>
											<NcActionButton close-after-click @click="addSourceAuthentication(source)">
												<template #icon>
													<Plus :size="20" />
												</template>
												Add Authentication
											</NcActionButton>
											<NcActionButton close-after-click @click="sourceStore.exportSource(source.id)">
												<template #icon>
													<FileExportOutline :size="20" />
												</template>
												Export
											</NcActionButton>
											<NcActionButton close-after-click @click="sourceStore.setSourceItem(source); navigationStore.setDialog('deleteSource')">
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
				v-if="filteredSources.length > 0"
				:current-page="pagination.page || 1"
				:total-pages="Math.ceil(filteredSources.length / (pagination.limit || 20))"
				:total-items="filteredSources.length"
				:current-page-size="pagination.limit || 20"
				:min-items-to-show="10"
				@page-changed="onPageChanged"
				@page-size-changed="onPageSizeChanged" />
		</div>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcLoadingIcon, NcActions, NcActionButton, NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import DatabaseArrowLeftOutline from 'vue-material-design-icons/DatabaseArrowLeftOutline.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import Sync from 'vue-material-design-icons/Sync.vue'
import FileExportOutline from 'vue-material-design-icons/FileExportOutline.vue'
import FileImportOutline from 'vue-material-design-icons/FileImportOutline.vue'

import PaginationComponent from '../../components/PaginationComponent.vue'

export default {
	name: 'SourcesIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		NcActions,
		NcActionButton,
		NcCheckboxRadioSwitch,
		NcButton,
		DatabaseArrowLeftOutline,
		DotsHorizontal,
		Pencil,
		TrashCanOutline,
		Refresh,
		Plus,
		Eye,
		Sync,
		FileExportOutline,
		FileImportOutline,
		PaginationComponent,
	},
	data() {
		return {
			selectedSources: [],
			pagination: {
				page: 1,
				limit: 20,
			},
		}
	},
	computed: {
		filteredSources() {
			if (!sourceStore.sourceList) return []
			return sourceStore.sourceList
		},
		paginatedSources() {
			const start = ((this.pagination.page || 1) - 1) * (this.pagination.limit || 20)
			const end = start + (this.pagination.limit || 20)
			return this.filteredSources.slice(start, end)
		},
		allSelected() {
			return this.filteredSources.length > 0 && this.filteredSources.every(source => this.selectedSources.includes(source.id))
		},
		someSelected() {
			return this.selectedSources.length > 0 && !this.allSelected
		},
		emptyContentName() {
			if (sourceStore.loading) {
				return t('openconnector', 'Loading sources...')
			} else if (sourceStore.error) {
				return sourceStore.error
			} else if (!sourceStore.sourceList?.length) {
				return t('openconnector', 'No sources found')
			}
			return ''
		},
		emptyContentDescription() {
			if (sourceStore.loading) {
				return t('openconnector', 'Please wait while we fetch your sources.')
			} else if (sourceStore.error) {
				return t('openconnector', 'Please try again later.')
			} else if (!sourceStore.sourceList?.length) {
				return t('openconnector', 'No sources are available.')
			}
			return ''
		},
	},
	mounted() {
		sourceStore.refreshSourceList()
	},
	methods: {
		toggleSelectAll(checked) {
			if (checked) {
				this.selectedSources = this.filteredSources.map(source => source.id)
			} else {
				this.selectedSources = []
			}
		},
		toggleSourceSelection(sourceId, checked) {
			if (checked) {
				this.selectedSources.push(sourceId)
			} else {
				this.selectedSources = this.selectedSources.filter(id => id !== sourceId)
			}
		},
		onPageChanged(page) {
			this.pagination.page = page
		},
		onPageSizeChanged(pageSize) {
			this.pagination.page = 1
			this.pagination.limit = pageSize
		},
		getConfigurationCount(source) {
			const config = source.configuration || {}
			const { authentication, ...configWithoutAuth } = config
			return Object.keys(configWithoutAuth).length
		},
		getAuthenticationCount(source) {
			const authentication = source.configuration?.authentication || {}
			return Object.keys(authentication).length
		},
		addSourceConfiguration(source) {
			sourceStore.setSourceItem(source)
			sourceStore.setSourceConfigurationKey(null)
			navigationStore.setModal('editSourceConfiguration')
		},
		addSourceAuthentication(source) {
			sourceStore.setSourceItem(source)
			sourceStore.setSourceConfigurationKey(null)
			navigationStore.setModal('editSourceConfigurationAuthentication')
		},
	},
}
</script>

<style scoped>
.sourceDetails {
	margin-top: 1rem;
}

.sourceDescription {
	color: var(--color-text-lighter);
	margin-bottom: 1rem;
}

.truncatedUrl {
	max-width: 200px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	display: inline-block;
}
</style>
