<script setup>
import { navigationStore } from '../store/store.js'
</script>

<template>
	<div>
		<!-- Source Logs Sidebar -->
		<SourceLogSideBar v-if="navigationStore.selected === 'source-logs'" />

		<!-- Contracts Sidebar -->
		<ContractsSideBar v-if="navigationStore.selected === 'contracts'" />

		<!-- Logs Sidebar -->
		<LogsSideBar v-if="navigationStore.selected === 'logs'" />
	</div>
</template>

<script>
import SourceLogSideBar from './Source/SourceLogSideBar.vue'
import ContractsSideBar from './contracts/ContractsSideBar.vue'
import LogsSideBar from './logs/LogsSideBar.vue'

export default {
	name: 'SideBars',
	components: {
		SourceLogSideBar,
		ContractsSideBar,
		LogsSideBar,
	},
}
</script>
