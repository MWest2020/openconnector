<script setup>
import { sourceStore, navigationStore, logStore, synchronizationStore } from '../../store/store.js'
</script>

<template>
	<NcModal v-if="navigationStore.modal === 'viewSource'"
		ref="modalRef"
		label-id="viewSource"
		@close="navigationStore.setModal(false)">
		<div class="modal-content">
			<h2>{{ sourceStore.sourceItem?.name || t('openconnector', 'Source Details') }}</h2>
			<p v-if="sourceStore.sourceItem?.description" class="source-description">
				{{ sourceStore.sourceItem.description }}
			</p>

			<!-- Source Properties -->
			<div class="source-properties">
				<div class="property-grid">
					<div class="property-item">
						<strong>{{ t('openconnector', 'ID') }}:</strong>
						<span>{{ sourceStore.sourceItem?.id || sourceStore.sourceItem?.uuid || '-' }}</span>
					</div>
					<div class="property-item">
						<strong>{{ t('openconnector', 'Type') }}:</strong>
						<span>{{ sourceStore.sourceItem?.type || '-' }}</span>
					</div>
					<div class="property-item">
						<strong>{{ t('openconnector', 'Location') }}:</strong>
						<span>{{ sourceStore.sourceItem?.location || '-' }}</span>
					</div>
					<div class="property-item">
						<strong>{{ t('openconnector', 'Version') }}:</strong>
						<span>{{ sourceStore.sourceItem?.version || '-' }}</span>
					</div>
					<div class="property-item">
						<strong>{{ t('openconnector', 'Created') }}:</strong>
						<span>{{ sourceStore.sourceItem?.created ? new Date(sourceStore.sourceItem.created).toLocaleString() : '-' }}</span>
					</div>
					<div class="property-item">
						<strong>{{ t('openconnector', 'Updated') }}:</strong>
						<span>{{ sourceStore.sourceItem?.updated ? new Date(sourceStore.sourceItem.updated).toLocaleString() : '-' }}</span>
					</div>
				</div>
			</div>

			<!-- Tabs -->
			<NcAppNavigationTab id="configurations-tab" name="Configurations" :order="1">
				<template #icon>
					<FileCogOutline :size="20" />
				</template>
			</NcAppNavigationTab>
			<NcAppNavigationTab id="authentication-tab" name="Authentication" :order="2">
				<template #icon>
					<KeyOutline :size="20" />
				</template>
			</NcAppNavigationTab>
			<NcAppNavigationTab id="synchronizations-tab" name="Synchronizations" :order="3">
				<template #icon>
					<VectorPolylinePlus :size="20" />
				</template>
			</NcAppNavigationTab>
			<NcAppNavigationTab id="logs-tab" name="Logs" :order="4">
				<template #icon>
					<TimelineQuestionOutline :size="20" />
				</template>
			</NcAppNavigationTab>

			<div class="tab-content">
				<!-- Configurations Tab -->
				<div v-if="activeTab === 'configurations-tab'" class="tab-panel">
					<div v-if="Object.keys(configuration)?.length" class="configurations-list">
						<NcListItem v-for="(value, key, i) in configuration"
							:key="`${key}${i}`"
							:name="key"
							:bold="false"
							:force-display-actions="true"
							:active="sourceStore.sourceConfigurationKey === key"
							@click="setActiveSourceConfigurationKey(key)">
							<template #icon>
								<FileCogOutline :class="sourceStore.sourceConfigurationKey === key && 'selectedIcon'" :size="44" />
							</template>
							<template #subname>
								{{ value }}
							</template>
							<template #actions>
								<NcActionButton close-after-click @click="editSourceConfiguration(key)">
									<template #icon>
										<Pencil :size="20" />
									</template>
									Edit
								</NcActionButton>
								<NcActionButton close-after-click @click="deleteSourceConfiguration(key)">
									<template #icon>
										<Delete :size="20" />
									</template>
									Delete
								</NcActionButton>
							</template>
						</NcListItem>
					</div>
					<NcEmptyContent v-else
						:name="t('openconnector', 'No configurations')"
						:description="t('openconnector', 'No configurations found for this source')">
						<template #icon>
							<FileCogOutline :size="64" />
						</template>
						<template #action>
							<NcButton @click="addSourceConfiguration">
								{{ t('openconnector', 'Add Configuration') }}
							</NcButton>
						</template>
					</NcEmptyContent>
				</div>

				<!-- Authentication Tab -->
				<div v-if="activeTab === 'authentication-tab'" class="tab-panel">
					<div v-if="Object.keys(configurationAuthentication)?.length" class="authentication-list">
						<NcListItem v-for="(value, key, i) in configurationAuthentication"
							:key="`${key}${i}`"
							:name="key"
							:bold="false"
							:force-display-actions="true"
							:active="sourceStore.sourceConfigurationKey === key">
							<template #icon>
								<KeyOutline :class="sourceStore.sourceConfigurationKey === key && 'selectedIcon'" :size="44" />
							</template>
							<template #subname>
								{{ value }}
							</template>
							<template #actions>
								<NcActionButton close-after-click @click="sourceStore.setSourceConfigurationKey(key); navigationStore.setModal('editSourceConfigurationAuthentication')">
									<template #icon>
										<Pencil :size="20" />
									</template>
									Edit
								</NcActionButton>
								<NcActionButton close-after-click @click="sourceStore.setSourceConfigurationKey(key); navigationStore.setModal('deleteSourceConfigurationAuthentication')">
									<template #icon>
										<Delete :size="20" />
									</template>
									Delete
								</NcActionButton>
							</template>
						</NcListItem>
					</div>
					<NcEmptyContent v-else
						:name="t('openconnector', 'No authentication')"
						:description="t('openconnector', 'No authentication configurations found for this source')">
						<template #icon>
							<KeyOutline :size="64" />
						</template>
						<template #action>
							<NcButton @click="addSourceAuthentication">
								{{ t('openconnector', 'Add Authentication') }}
							</NcButton>
						</template>
					</NcEmptyContent>
				</div>

				<!-- Synchronizations Tab -->
				<div v-if="activeTab === 'synchronizations-tab'" class="tab-panel">
					<div v-if="linkedSynchronizations?.length" class="synchronizations-list">
						<NcListItem v-for="sync in linkedSynchronizations"
							:key="sync.id"
							:name="sync.name"
							:bold="false"
							:force-display-actions="true">
							<template #icon>
								<VectorPolylinePlus :size="44" />
							</template>
							<template #subname>
								{{ sync.description }}
							</template>
							<template #actions>
								<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(sync); navigationStore.setSelected('synchronizations')">
									<template #icon>
										<EyeOutline :size="20" />
									</template>
									View
								</NcActionButton>
								<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(sync); navigationStore.setModal('editSynchronization')">
									<template #icon>
										<Pencil :size="20" />
									</template>
									Edit
								</NcActionButton>
								<NcActionButton close-after-click @click="synchronizationStore.setSynchronizationItem(sync); navigationStore.setDialog('deleteSynchronization')">
									<template #icon>
										<Delete :size="20" />
									</template>
									Delete
								</NcActionButton>
							</template>
						</NcListItem>
					</div>
					<NcEmptyContent v-else
						:name="t('openconnector', 'No synchronizations')"
						:description="t('openconnector', 'No synchronizations found for this source')">
						<template #icon>
							<VectorPolylinePlus :size="64" />
						</template>
					</NcEmptyContent>
				</div>

				<!-- Logs Tab -->
				<div v-if="activeTab === 'logs-tab'" class="tab-panel">
					<div v-if="sourceStore.sourceLogs?.length" class="logs-list">
						<NcListItem v-for="(log, i) in sourceStore.sourceLogs"
							:key="log.id + i"
							:class="checkIfStatusIsOk(log.statusCode) ? 'okStatus' : 'errorStatus'"
							:name="`${log.statusMessage} ${log.response?.responseTime ? `(response time: ${(log.response.responseTime / 1000).toFixed(3)} seconds)` : ''}`"
							:bold="false"
							:counter-number="log.statusCode"
							:force-display-actions="true"
							:active="logStore.activeLogKey === `sourceLog-${log.id}`"
							@click="setActiveSourceLog(log.id)">
							<template #icon>
								<TimelineQuestionOutline :size="44" />
							</template>
							<template #subname>
								{{ new Date(log.created).toLocaleString() }}
							</template>
							<template #actions>
								<NcActionButton close-after-click @click="viewLog(log)">
									<template #icon>
										<EyeOutline :size="20" />
									</template>
									View
								</NcActionButton>
							</template>
						</NcListItem>
					</div>
					<NcEmptyContent v-else
						:name="t('openconnector', 'No logs')"
						:description="t('openconnector', 'No logs found for this source')">
						<template #icon>
							<TimelineQuestionOutline :size="64" />
						</template>
					</NcEmptyContent>
				</div>
			</div>

			<!-- Action buttons -->
			<div class="modal-actions">
				<NcButton @click="navigationStore.setModal('editSource')">
					<template #icon>
						<Pencil :size="20" />
					</template>
					{{ t('openconnector', 'Edit Source') }}
				</NcButton>
				<NcButton @click="navigationStore.setModal('testSource')">
					<template #icon>
						<Sync :size="20" />
					</template>
					{{ t('openconnector', 'Test Source') }}
				</NcButton>
				<NcButton @click="sourceStore.exportSource(sourceStore.sourceItem.id)">
					<template #icon>
						<FileExportOutline :size="20" />
					</template>
					{{ t('openconnector', 'Export Source') }}
				</NcButton>
				<NcButton type="error" @click="navigationStore.setDialog('deleteSource')">
					<template #icon>
						<TrashCanOutline :size="20" />
					</template>
					{{ t('openconnector', 'Delete Source') }}
				</NcButton>
				<NcButton @click="navigationStore.setModal(false)">
					{{ t('openconnector', 'Close') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcListItem, NcActionButton, NcEmptyContent, NcAppNavigationTab } from '@nextcloud/vue'
import FileCogOutline from 'vue-material-design-icons/FileCogOutline.vue'
import KeyOutline from 'vue-material-design-icons/KeyOutline.vue'
import VectorPolylinePlus from 'vue-material-design-icons/VectorPolylinePlus.vue'
import TimelineQuestionOutline from 'vue-material-design-icons/TimelineQuestionOutline.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'
import Sync from 'vue-material-design-icons/Sync.vue'
import FileExportOutline from 'vue-material-design-icons/FileExportOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'ViewSource',
	components: {
		NcModal,
		NcButton,
		NcListItem,
		NcActionButton,
		NcEmptyContent,
		NcAppNavigationTab,
		FileCogOutline,
		KeyOutline,
		VectorPolylinePlus,
		TimelineQuestionOutline,
		Pencil,
		Delete,
		EyeOutline,
		Sync,
		FileExportOutline,
		TrashCanOutline,
	},
	data() {
		return {
			activeTab: 'configurations-tab',
		}
	},
	computed: {
		configuration() {
			const config = sourceStore.sourceItem?.configuration || {}
			const { authentication, ...configWithoutAuth } = config
			return configWithoutAuth
		},
		configurationAuthentication() {
			return sourceStore.sourceItem?.configuration?.authentication || {}
		},
		linkedSynchronizations() {
			return synchronizationStore.synchronizationList?.filter((item) =>
				item.sourceId.toString() === sourceStore.sourceItem?.id?.toString(),
			) || []
		},
	},
	mounted() {
		this.refreshSourceLogs()
		synchronizationStore.refreshSynchronizationList()
	},
	methods: {
		deleteSourceConfiguration(key) {
			sourceStore.setSourceConfigurationKey(key)
			navigationStore.setModal('deleteSourceConfiguration')
		},
		editSourceConfiguration(key) {
			sourceStore.setSourceConfigurationKey(key)
			navigationStore.setModal('editSourceConfiguration')
		},
		addSourceConfiguration() {
			sourceStore.setSourceConfigurationKey(null)
			navigationStore.setModal('editSourceConfiguration')
		},
		addSourceAuthentication() {
			sourceStore.setSourceConfigurationKey(null)
			navigationStore.setModal('editSourceConfigurationAuthentication')
		},
		viewLog(log) {
			logStore.setViewLogItem(log)
			navigationStore.setModal('viewSourceLog')
		},
		setActiveSourceConfigurationKey(sourceConfigurationKey) {
			if (sourceStore.sourceConfigurationKey === sourceConfigurationKey) {
				sourceStore.setSourceConfigurationKey(false)
			} else {
				sourceStore.setSourceConfigurationKey(sourceConfigurationKey)
			}
		},
		setActiveSourceLog(sourceLogId) {
			if (logStore.activeLogKey === `sourceLog-${sourceLogId}`) {
				logStore.setActiveLogKey(null)
			} else {
				logStore.setActiveLogKey(`sourceLog-${sourceLogId}`)
			}
		},
		refreshSourceLogs() {
			sourceStore.refreshSourceLogs()
		},
		checkIfStatusIsOk(statusCode) {
			if (statusCode > 199 && statusCode < 300) {
				return true
			}
			return false
		},
	},
}
</script>

<style scoped>
.modal-content {
	padding: 20px;
	max-width: 800px;
	max-height: 80vh;
	overflow-y: auto;
}

.source-description {
	color: var(--color-text-maxcontrast);
	margin-bottom: 20px;
	font-style: italic;
}

.source-properties {
	margin-bottom: 20px;
	padding: 15px;
	background-color: var(--color-background-hover);
	border-radius: var(--border-radius);
}

.property-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 10px;
}

.property-item {
	display: flex;
	gap: 8px;
}

.property-item strong {
	min-width: 80px;
	color: var(--color-text-maxcontrast);
}

.tab-content {
	min-height: 300px;
	margin: 20px 0;
}

.tab-panel {
	padding: 15px 0;
}

.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid var(--color-border);
}

.selectedIcon {
	color: var(--color-primary);
}

:deep(.okStatus .counter-bubble__counter) {
	background-color: #69b090;
	color: white;
}

:deep(.errorStatus .counter-bubble__counter) {
	background-color: #dd3c49;
	color: white;
}
</style>
