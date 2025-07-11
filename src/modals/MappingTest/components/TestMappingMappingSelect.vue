<script setup>
import { mappingStore } from '../../../store/store.js'
</script>

<template>
	<div>
		<div v-if="!openRegister.isInstalled && !closeAlert" class="openregister-notecard">
			<NcNoteCard
				:type="openRegister.isAvailable ? 'info' : 'error'"
				:heading="openRegister.isAvailable ? 'Open Register is not installed' : 'Failed to install Open Register'">
				<p>
					{{ openRegister.isAvailable
						? 'Some features require Open Register to be installed'
						: 'This either means that Open Register is not available on this server or you need to confirm your password' }}
				</p>

				<div class="install-buttons">
					<NcButton v-if="openRegister.isAvailable"
						aria-label="Install OpenRegister"
						size="small"
						type="primary"
						@click="installOpenRegister">
						<template #icon>
							<CloudDownload :size="20" />
						</template>
						Install OpenRegister
					</NcButton>
					<NcButton
						aria-label="Install OpenRegister Manually"
						size="small"
						type="secondary"
						@click="openLink('/index.php/settings/apps/organization/openregister', '_blank')">
						<template #icon>
							<OpenInNew :size="20" />
						</template>
						Install OpenRegister Manually
					</NcButton>
				</div>
				<div class="close-button">
					<NcActions>
						<NcActionButton close-after-click @click="closeAlert = true">
							<template #icon>
								<Close :size="20" />
							</template>
							Close
						</NcActionButton>
					</NcActions>
				</div>
			</NcNoteCard>
		</div>

		<h4>Test mapping</h4>

		<div class="content">
			<div class="mapping-select">
				<NcSelect v-bind="mappings"
					v-model="mappings.value"
					input-label="Mapping"
					:clearable="false"
					:loading="mappingsLoading || mappingTest.loading"
					required
					@input="emitMappingSelected">
					<!-- eslint-disable-next-line vue/no-unused-vars vue/no-template-shadow  -->
					<template #no-options="{ search, searching, loading }">
						<p v-if="loading">
							Loading...
						</p>
						<p v-if="!loading && !mappings.options?.length">
							Er zijn geen mappings beschikbaar
						</p>
					</template>
					<!-- eslint-disable-next-line vue/no-unused-vars  -->
					<template #option="{ id, label, summary, removeStyle }">
						<div :class="removeStyle !== true && 'mapping-option'">
							<!-- custom style is enabled -->
							<SitemapOutline v-if="!removeStyle" :size="25" />
							<span v-if="!removeStyle">
								<h6 style="margin: 0">
									{{ label }}
								</h6>
								{{ summary }}
							</span>
							<!-- custom style is disabled -->
							<p v-if="removeStyle">
								{{ label }}
							</p>
						</div>
					</template>
				</NcSelect>

				<NcSelect v-bind="schemas"
					v-model="schemas.value"
					input-label="Schema"
					:loading="schemasLoading"
					:disabled="!openRegister.isInstalled"
					required
					@input="emitSchemaSelected">
					<!-- eslint-disable-next-line vue/no-unused-vars vue/no-template-shadow  -->
					<template #no-options="{ search, searching, loading }">
						<p v-if="loading">
							Loading...
						</p>
						<p v-if="!loading && !schemas.options?.length">
							Er zijn geen schemas beschikbaar
						</p>
					</template>
					<!-- eslint-disable-next-line vue/no-unused-vars  -->
					<template #option="{ id, label, fullSchema, removeStyle }">
						<div :class="removeStyle !== true && 'mapping-option'">
							<!-- custom style is enabled -->
							<FileTreeOutline v-if="!removeStyle" :size="25" />
							<span v-if="!removeStyle">
								<h6 style="margin: 0">
									{{ label }}
								</h6>
								{{ fullSchema.summary }}
							</span>
							<!-- custom style is disabled -->
							<p v-if="removeStyle">
								{{ label }}
							</p>
						</div>
					</template>
				</NcSelect>
			</div>

			<div class="edit-mapping">
				<h4>Edit mapping</h4>

				<NcTextField :value.sync="mappingItem.name"
					label="name" />

				<NcTextArea
					resize="vertical"
					:value.sync="mappingItem.description"
					label="description" />

				<NcTextArea
					resize="vertical"
					:value.sync="mappingItem.mapping"
					label="mapping"
					:error="!validJson(mappingItem.mapping)"
					:helper-text="!validJson(mappingItem.mapping) ? 'Invalid JSON' : ''" />

				<NcTextArea
					resize="vertical"
					:value.sync="mappingItem.cast"
					label="cast"
					:error="!validJson(mappingItem.cast, true)"
					:helper-text="!validJson(mappingItem.cast, true) ? 'Invalid JSON' : ''" />

				<NcTextArea
					resize="vertical"
					:value.sync="mappingItem.unset"
					label="unset"
					helper-text="Enter a comma-separated list of keys." />

				<div class="buttons">
					<NcButton class="reset-button"
						type="secondary"
						@click="setupEditFields(mappings.value?.id)">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Reset
					</NcButton>
					<NcButton class="save-button"
						type="primary"
						@click="saveMappingChanges()">
						<template #icon>
							<NcLoadingIcon v-if="savingMapping" :size="20" />
							<ContentSaveOutline v-if="!savingMapping" :size="20" />
						</template>
						Save
					</NcButton>

					<NcButton :disabled="mappingTest.loading || !mappings.value || !inputObject.isValid || !validJson(mappingItem.mapping) || !validJson(mappingItem.cast, true)"
						class="test-button"
						type="success"
						@click="testMapping()">
						<template #icon>
							<NcLoadingIcon v-if="mappingTest.loading" :size="20" />
							<TestTube v-if="!mappingTest.loading" :size="20" />
						</template>
						Test
					</NcButton>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import {
	NcSelect,
	NcTextField,
	NcTextArea,
	NcButton,
	NcActions,
	NcActionButton,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

import CloudDownload from 'vue-material-design-icons/CloudDownload.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import Close from 'vue-material-design-icons/Close.vue'

import SitemapOutline from 'vue-material-design-icons/SitemapOutline.vue'
import FileTreeOutline from 'vue-material-design-icons/FileTreeOutline.vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import TestTube from 'vue-material-design-icons/TestTube.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'

import { Mapping } from '../../../entities/index.js'

import openLink from '../../../services/openLink.js'

export default {
	name: 'TestMappingMappingSelect',
	components: {
		NcSelect,
		NcTextField,
		NcTextArea,
		NcButton,
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		NcNoteCard,
	},
	props: {
		inputObject: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			mappings: [],
			mappingsLoading: false,
			mappingItem: {
				name: '',
				description: '',
				mapping: '{}',
				cast: '{}',
				unset: '', // array as string
			},
			// use uniqueMappingId as the "No mapping" option's ID to avoid any possible truthy comparisons
			uniqueMappingId: Symbol('No Mapping'), // Symbol creates a truly unique value, so unique making 2 of the same symbol will never be the same.
			savingMapping: false,
			savingMappingSuccess: null,
			// mapping test
			mappingTest: {
				result: {}, // result from the testMapping function
				success: null,
				loading: false,
				error: false,
			},
			schemas: [],
			schemasLoading: false,
			openRegister: {
				isInstalled: true,
				isAvailable: true,
			},
			closeAlert: false,
		}
	},
	watch: {
		'mappings.value.id'(newVal) {
			this.setupEditFields(newVal)
		},
		// watch data and emit
		mappingTest: {
			handler(newVal) {
				this.$emit('mapping-test', {
					...newVal,
				})
			},
			deep: true,
		},
		mappingsLoading(newVal) {
			this.$emit('mapping-selected', {
				loading: newVal,
			})
		},
		schemasLoading(newVal) {
			this.$emit('schema-selected', {
				loading: newVal,
			})
		},
	},
	mounted() {
		this.fetchMappings()
		this.fetchSchemas()
	},
	methods: {
		emitMappingSelected(event) {
			this.$emit('mapping-selected', {
				selected: event,
			})
		},
		emitSchemaSelected(event) {
			this.$emit('schema-selected', {
				selected: event,
			})
		},
		setupEditFields(id) {
			if (id === this.uniqueMappingId) { // "No mapping" option selected (Symbol comparisons can only return true if its the same symbol from the same variable)
				this.mappingItem = {
					name: '',
					description: '',
					mapping: '{}',
					cast: '{}',
					unset: '',
				}
			} else {
				this.mappingItem.name = this.mappings.value.fullMapping.name
				this.mappingItem.description = this.mappings.value.fullMapping.description
				this.mappingItem.mapping = JSON.stringify(this.mappings.value.fullMapping.mapping, null, 2)
				this.mappingItem.cast = JSON.stringify(this.mappings.value.fullMapping.cast, null, 2)
				this.mappingItem.unset = this.mappings.value.fullMapping.unset.join(', ') // turn the array into a string
			}
		},
		async fetchMappings(currentMappingItem = null) {
			this.mappingsLoading = true

			return mappingStore.refreshMappingList()
				.then(() => {
					if (!currentMappingItem) {
						currentMappingItem = mappingStore.mappingItem || null
					}

					const selectedMapping = mappingStore.mappingList.find((mapping) => mapping.id === (currentMappingItem?.id || Symbol('mapping item id not found')))

					const fallbackMapping = mappingStore.mappingList[0]
						? {
							id: mappingStore.mappingList[0].id,
							label: mappingStore.mappingList[0].name,
							summary: mappingStore.mappingList[0].description,
							fullMapping: mappingStore.mappingList[0],
						}
						: null

					this.mappings = {
						options: [
							{
								id: this.uniqueMappingId,
								label: 'No mapping',
								removeStyle: true,
							},
							...mappingStore.mappingList.map((mapping) => ({
								id: mapping.id,
								label: mapping.name,
								summary: mapping.description,
								fullMapping: mapping,
							})),
						],
						value: selectedMapping
							? {
								id: selectedMapping.id,
								label: selectedMapping.name,
								summary: selectedMapping.description,
								fullMapping: selectedMapping,
							}
							: fallbackMapping,
					}

					// emit the current selected mapping after mappings initialization
					this.$emit('mapping-selected', {
						mappings: this.mappings,
						selected: this.mappings.value,
					})
				})
				.finally(() => {
					this.mappingsLoading = false
				})
		},
		async fetchSchemas() {
			this.schemasLoading = true

			// checking if OpenRegister is installed
			console.info('Fetching schemas from Open Register')
			const response = await fetch('/index.php/apps/openregister/api/schemas', {
				headers: {
					accept: '*/*',
					'accept-language': 'en-US,en;q=0.9,nl;q=0.8',
					'cache-control': 'no-cache',
					pragma: 'no-cache',
					'x-requested-with': 'XMLHttpRequest',
				},
				referrerPolicy: 'no-referrer',
				body: null,
				method: 'GET',
				mode: 'cors',
				credentials: 'include',
			})

			if (!response.ok) {
				console.info('Open Register is not installed')
				this.schemasLoading = false
				this.$emit('open-register', {
					isInstalled: false,
				})
				return
			}

			const responseData = (await response.json()).results

			this.schemas = {
				options: responseData.map((schema) => ({
					id: schema.id,
					label: schema.title,
					fullSchema: schema,
				})),
				value: null,
			}

			// emit the current selected mapping after mappings initialization
			this.$emit('schema-selected', {
				schemas: this.schemas,
				selected: this.schemas.value,
			})

			this.schemasLoading = false
		},
		async testMapping() {
			this.mappingTest.loading = true
			this.mappingTest.error = false
			this.mappingTest.success = null
			this.mappingTest.result = {}

			mappingStore.testMapping({
				mapping: {
					...this.mappings.value.fullMapping,
					name: this.mappingItem.name,
					description: this.mappingItem.description,
					mapping: JSON.parse(this.mappingItem.mapping),
					cast: this.mappingItem.cast ? JSON.parse(this.mappingItem.cast) : null,
					unset: this.mappingItem.unset.split(/ *, */g).filter(Boolean),
				},
				inputObject: JSON.parse(this.inputObject.value),
				schema: this.schemas.value?.id,
			})
				.then(({ response, data }) => {
					this.mappingTest.success = response.ok
					this.mappingTest.result = data
				})
				.catch((error) => {
					this.mappingTest.error = error.message || 'An error occurred while testing the mapping'
				})
				.finally(() => {
					this.mappingTest.loading = false
				})
		},
		saveMappingChanges() {
			this.savingMapping = true

			const newMappingItem = new Mapping({
				...this.mappings.value?.fullMapping,
				name: this.mappingItem.name,
				description: this.mappingItem.description,
				mapping: JSON.parse(this.mappingItem.mapping),
				cast: JSON.parse(this.mappingItem.cast),
				unset: this.mappingItem.unset.split(/ *, */g).filter(Boolean),
			})

			mappingStore.saveMapping(newMappingItem)
				.then(({ response, entity }) => {
					this.savingMappingSuccess = response.ok
					response.ok && this.fetchMappings(entity)
						.then(() => {
							this.setupEditFields(entity.id)
						})
				})
				.catch((e) => {
					this.savingMappingSuccess = false
				})
				.finally(() => {
					setTimeout(() => (this.savingMappingSuccess = null), 2000)
					this.savingMapping = false
				})
		},
		async installOpenRegister() {
			console.info('Installing Open Register')
			const token = document.querySelector('head[data-requesttoken]').getAttribute('data-requesttoken')

			const response = await fetch('/index.php/settings/apps/enable', {
				headers: {
					accept: '*/*',
					'accept-language': 'en-US,en;q=0.9,nl;q=0.8',
					'cache-control': 'no-cache',
					'content-type': 'application/json',
					pragma: 'no-cache',
					requesttoken: token,
					'x-requested-with': 'XMLHttpRequest, XMLHttpRequest',
				},
				referrerPolicy: 'no-referrer',
				body: '{"appIds":["openregister"],"groups":[]}',
				method: 'POST',
				mode: 'cors',
				credentials: 'include',
			})

			if (!response.ok) {
				console.info('Failed to install Open Register')
				this.openRegister.isAvailable = false
			} else {
				console.info('Open Register installed')
				this.openRegister.isInstalled = true
				this.fetchSchemas()
			}
		},
		validJson(object, optional = false) {
			if (optional && !object) {
				return true
			}

			try {
				JSON.parse(object)
				return true
			} catch (e) {
				return false
			}
		},
	},
}
</script>

<style scoped>
/* close button for notecard */
.openregister-notecard .notecard {
    position: relative;
}
.close-button {
    position: absolute;
    top: 5px;
    right: 5px;
}
.close-button .button-vue--vue-tertiary:hover:not(:disabled) {
    background-color: rgba(var(--color-info-rgb), 0.1);
}

.content {
    text-align: left;
}

.textarea :deep(textarea) {
    resize: vertical !important;
    height: 100%;
}

.mapping-select {
    display: grid;
	grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.mapping-select > .v-select {
    min-width: auto;
}

.mapping-select > .button-vue {
    margin-block-end: 4px !important;
}

.install-buttons {
    display: flex;
    gap: 0.5rem;
    margin-block-start: 1rem;
}

/* Mapping option */
.mapping-option {
    display: flex;
    align-items: center;
    gap: 10px;
}
.mapping-option > .material-design-icon {
    margin-block-start: 2px;
}
.mapping-option > h6 {
    line-height: 0.8;
}

/* select style */
/* remove box-shadow around search input */
.v-select :deep(.vs__search) {
    box-shadow: none !important;
}

.edit-mapping > h4 {
    margin-block-start: 2rem !important;
    margin-block-end: 1rem !important;
}

.buttons {
    display: flex;
    gap: 0.5rem;
    margin-block-start: var(--OC-margin-10);
}

.test-button {
    margin-left: auto;
}
</style>
