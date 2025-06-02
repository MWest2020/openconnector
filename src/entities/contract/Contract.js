import ReadonlyBaseClass from '../ReadonlyBaseClass.js'

/**
 * Class representing a synchronization contract
 *
 * @description
 * This class represents a contract between a source and target object during synchronization.
 * It tracks the state of synchronized objects and their relationships.
 *
 * @class
 * @extends ReadonlyBaseClass
 */
export class Contract extends ReadonlyBaseClass {

	/**
	 * Creates an instance of Contract.
	 *
	 * @param {object} data - The contract data
	 */
	constructor(data) {
		// Process and normalize all data before calling super
		const processedData = {
			// Basic properties
			id: data.id || null,
			uuid: data.uuid || null,
			version: data.version || '0.0.1',
			synchronizationId: data.synchronizationId || data.synchronization_id || null,
			
			// Source/Origin properties
			originId: data.originId || data.origin_id || null,
			originHash: data.originHash || data.origin_hash || null,
			sourceLastChanged: data.sourceLastChanged || data.source_last_changed || null,
			sourceLastChecked: data.sourceLastChecked || data.source_last_checked || null,
			sourceLastSynced: data.sourceLastSynced || data.source_last_synced || null,
			
			// Target properties
			targetId: data.targetId || data.target_id || null,
			targetHash: data.targetHash || data.target_hash || null,
			targetLastChanged: data.targetLastChanged || data.target_last_changed || null,
			targetLastChecked: data.targetLastChecked || data.target_last_checked || null,
			targetLastSynced: data.targetLastSynced || data.target_last_synced || null,
			targetLastAction: data.targetLastAction || data.target_last_action || null,
			
			// Timestamps
			created: data.created || null,
			updated: data.updated || null
		}

		// Add computed/virtual properties
		processedData.name = data.name || this.generateContractNameStatic(processedData)
		processedData.description = data.description || null
		processedData.status = data.status || this.calculateStatusStatic(processedData)
		processedData.lastExecuted = data.lastExecuted || processedData.sourceLastSynced || processedData.targetLastSynced || null
		processedData.nextExecution = data.nextExecution || null
		processedData.totalExecutions = data.totalExecutions || 0
		processedData.successfulExecutions = data.successfulExecutions || 0

		// Call parent constructor with processed data
		super(processedData)
	}

	/**
	 * Generate a human-readable contract name (static version for constructor)
	 *
	 * @param {object} data - The contract data
	 * @return {string} Generated contract name
	 */
	generateContractNameStatic(data) {
		if (data.originId && data.targetId) {
			return `Contract: ${data.originId} → ${data.targetId}`
		} else if (data.originId) {
			return `Contract: ${data.originId} → (pending)`
		} else if (data.id) {
			return `Contract ${data.id}`
		}
		return 'New Contract'
	}

	/**
	 * Calculate contract status based on sync dates and data (static version for constructor)
	 *
	 * @param {object} data - The contract data
	 * @return {string} Contract status (active, inactive, error)
	 */
	calculateStatusStatic(data) {
		// If we have recent sync activity, consider it active
		if (data.sourceLastSynced || data.targetLastSynced) {
			const lastSync = new Date(Math.max(
				new Date(data.sourceLastSynced || 0),
				new Date(data.targetLastSynced || 0)
			))
			const daysSinceSync = (Date.now() - lastSync.getTime()) / (1000 * 60 * 60 * 24)
			
			if (daysSinceSync < 7) {
				return 'active'
			}
		}
		
		// If we have both origin and target, it's at least inactive
		if (data.originId && data.targetId) {
			return 'inactive'
		}
		
		// If missing critical data, consider it an error
		return 'error'
	}

	/**
	 * Get a human-readable status label
	 *
	 * @return {string} Status label
	 */
	getStatusLabel() {
		switch (this.status) {
			case 'active':
				return 'Active'
			case 'inactive':
				return 'Inactive'
			case 'error':
				return 'Error'
			default:
				return 'Unknown'
		}
	}

	/**
	 * Check if the contract is ready for synchronization
	 *
	 * @return {boolean} True if ready for sync
	 */
	isReadyForSync() {
		return !!(this.synchronizationId && this.originId)
	}

	/**
	 * Get the time since last synchronization in milliseconds
	 *
	 * @return {number|null} Milliseconds since last sync, or null if never synced
	 */
	getTimeSinceLastSync() {
		const lastSync = this.getLastSyncDate()
		return lastSync ? Date.now() - lastSync.getTime() : null
	}

	/**
	 * Get the most recent synchronization date
	 *
	 * @return {Date|null} Most recent sync date or null
	 */
	getLastSyncDate() {
		const dates = [this.sourceLastSynced, this.targetLastSynced].filter(Boolean)
		if (dates.length === 0) return null
		
		return new Date(Math.max(...dates.map(d => new Date(d).getTime())))
	}

	/**
	 * Convert contract to JSON representation
	 *
	 * @return {object} JSON representation of the contract
	 */
	toJSON() {
		return {
			id: this.id,
			uuid: this.uuid,
			version: this.version,
			synchronizationId: this.synchronizationId,
			originId: this.originId,
			originHash: this.originHash,
			sourceLastChanged: this.sourceLastChanged,
			sourceLastChecked: this.sourceLastChecked,
			sourceLastSynced: this.sourceLastSynced,
			targetId: this.targetId,
			targetHash: this.targetHash,
			targetLastChanged: this.targetLastChanged,
			targetLastChecked: this.targetLastChecked,
			targetLastSynced: this.targetLastSynced,
			targetLastAction: this.targetLastAction,
			name: this.name,
			description: this.description,
			status: this.status,
			lastExecuted: this.lastExecuted,
			nextExecution: this.nextExecution,
			totalExecutions: this.totalExecutions,
			successfulExecutions: this.successfulExecutions,
			created: this.created,
			updated: this.updated
		}
	}

	/**
	 * Generate a human-readable contract name
	 *
	 * @return {string} Generated contract name
	 */
	generateContractName() {
		if (this.originId && this.targetId) {
			return `Contract: ${this.originId} → ${this.targetId}`
		} else if (this.originId) {
			return `Contract: ${this.originId} → (pending)`
		} else if (this.id) {
			return `Contract ${this.id}`
		}
		return 'New Contract'
	}

	/**
	 * Calculate contract status based on sync dates and data
	 *
	 * @return {string} Contract status (active, inactive, error)
	 */
	calculateStatus() {
		// If we have recent sync activity, consider it active
		if (this.sourceLastSynced || this.targetLastSynced) {
			const lastSync = new Date(Math.max(
				new Date(this.sourceLastSynced || 0),
				new Date(this.targetLastSynced || 0)
			))
			const daysSinceSync = (Date.now() - lastSync.getTime()) / (1000 * 60 * 60 * 24)
			
			if (daysSinceSync < 7) {
				return 'active'
			}
		}
		
		// If we have both origin and target, it's at least inactive
		if (this.originId && this.targetId) {
			return 'inactive'
		}
		
		// If missing critical data, consider it an error
		return 'error'
	}
}

/**
 * Type definition for contract data
 * @typedef {object} TContract
 * @property {number|null} id - Contract ID
 * @property {string|null} uuid - Contract UUID
 * @property {string} version - Contract version
 * @property {string|null} synchronizationId - Associated synchronization ID
 * @property {string|null} originId - Source object ID
 * @property {string|null} originHash - Source object hash
 * @property {string|null} sourceLastChanged - Source last changed timestamp
 * @property {string|null} sourceLastChecked - Source last checked timestamp
 * @property {string|null} sourceLastSynced - Source last synced timestamp
 * @property {string|null} targetId - Target object ID
 * @property {string|null} targetHash - Target object hash
 * @property {string|null} targetLastChanged - Target last changed timestamp
 * @property {string|null} targetLastChecked - Target last checked timestamp
 * @property {string|null} targetLastSynced - Target last synced timestamp
 * @property {string|null} targetLastAction - Last action performed on target
 * @property {string|null} name - Contract name
 * @property {string|null} description - Contract description
 * @property {string} status - Contract status
 * @property {string|null} lastExecuted - Last execution timestamp
 * @property {string|null} nextExecution - Next execution timestamp
 * @property {number} totalExecutions - Total number of executions
 * @property {number} successfulExecutions - Number of successful executions
 * @property {string|null} created - Creation timestamp
 * @property {string|null} updated - Last update timestamp
 */
export { Contract as TContract } 